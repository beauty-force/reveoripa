<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Plan;
use App\Models\Gacha;
use App\Models\Product;
use App\Models\Rank;
use App\Models\Gacha_lost_product;
use App\Models\Gacha_video;

use App\Http\Resources\GachaListResource;
use App\Http\Resources\ProductListResource;
use App\Http\Controllers\User\UserController;

use Str;
use File;
use DB;

class GachaController extends Controller
{
    public function index(Request $request) {
        $cat_id = $request->cat_id ? $request->cat_id : getCategories()[0]->id;
        $GachaObj = Gacha::where('category_id', $cat_id);
        if (auth()->user()->getType()=="staff") {
            $GachaObj->where('status', 0);
        }
        $gachas = $GachaObj->orderBy('order_level', 'DESC')->orderBy('id', 'DESC')->get();

        $gachas = GachaListResource::collection($gachas);
        return inertia('Admin/Gacha/Index', compact('gachas'));
    }

    public function store(Request $request) {
        $validatored = $request->validate([
            'point' => 'required',
            'count_card' => 'required|numeric',
            'lost_product_type' => '',
            'thumbnail' => 'required|image|max:4096',
            'category_id' => 'required',
        ]);

        if ($request->image) {
            $image = saveImage('images/gacha', $request->file('image'), false);
        }
        else $image = '';
        $thumbnail = saveImage('images/gacha/thumbnail', $request->file('thumbnail'), false);
        $data = [
            'point' => $request->point,
            'consume_point' => $request->point,
            'count_card' => $request->count_card,
            'lost_product_type' => $request->lost_product_type,
            'thumbnail' => $thumbnail,
            'image' => $image,
            'category_id' => $request->category_id,
            'spin_limit' => 0,
            'title' => $request->title,
        ];
        $gacha = Gacha::create($data);

        return redirect(combineRoute(route('admin.gacha.edit', $gacha->id), $request->category_id) ); 
    }

    public function create() {
        return inertia('Admin/Gacha/Create');
    }

    public function copy(Request $request) {
        $id = $request->gacha_id;
        $gacha = Gacha::find($id);
        $data = [
            'title' => $request->title,
            'point' => $request->point ? $gacha->point : 0,
            'consume_point' => $request->point ? $gacha->point : 0,
            'count_card' => $request->count_card ? $gacha->count_card : 0,
            'lost_product_type' => $gacha->lost_product_type,
            'thumbnail' => $request->thumbnail ? $gacha->thumbnail : '',
            'image' => $request->detail_image ? $gacha->image : '',
            'category_id' => $gacha->category_id,
            'spin_limit' => $request->spin_limit ? $gacha->spin_limit : 0,
        ];
        $new_gacha = Gacha::create($data);
        if ($request->videos) {
            $videos = Gacha_video::where('gacha_id', $id)->get();
            foreach ($videos as $video) {
                Gacha_video::create([
                    'level' => $video['level'],
                    'gacha_id' => $new_gacha->id,
                    'point' => $video['point'],
                    'file' => $video['file']
                ]);
            }
        }
        if ($request->cards) {
            $cards = Gacha_lost_product::select('point', 'count')->where('gacha_id', $id)->get();
            foreach ($cards as $card) {
                $data = ['gacha_id'=>$new_gacha->id, 'point'=>$card->point, 'count'=>$card->count];
                Gacha_lost_product::create($data);
            }
        }
        if ($request->last_product) {
            $product = Product::where('is_last', 1)->where('is_lost_product', 0)->where('gacha_id', $id)->first();
            if ($product) {
                $data = [
                    'name' => $product->name,
                    'point' => $product->point,
                    'rare' => $product->rare,
                    'image' => $product->image,
                    'gacha_id' => $new_gacha->id,
                    'is_last' => 1
                ];
                Product::create($data);
            }
        }
        if ($request->rare_product) {
            $products = Product::where('is_last', 0)->where('is_lost_product', 0)->where('gacha_id', $id)->get();
            foreach ($products as $product) {
                $data = [
                    'name' => $product->name,
                    'point' => $product->point,
                    'rare' => $product->rare,
                    'gacha_id' =>$new_gacha->id,
                    'marks' => $product->marks,
                    'image' => $product->image,
                    'is_last' => 0,
                    'rank' => $product->rank,
                    'order' => $product->order,
                ];
                Product::create($data);
            }
        }
        return redirect(combineRoute(route('admin.gacha.edit', $new_gacha->id), $request->category_id) ); 
    }

    public function edit($id) {
        $gacha = Gacha::find($id);
        if (auth()->user()->getType()=="staff" && $gacha->status!=0) {
            $text = "権限がありません！";
            return inertia('NoProduct', compact('text'));
        }

        $gacha->image = getGachaImageUrl($gacha->image);
        $gacha->thumbnail = getGachaThumbnailUrl($gacha->thumbnail);
        $product_last = $gacha->getProductLast();

        $products = $gacha->getProducts();
        $products = ProductListResource::collection($products);
        $productsLostSetting = $gacha->productsLostSetting;

        $ranks = Rank::orderby('rank')->get();
        $videos = Gacha_video::where('gacha_id', $id)->orderBy('point')->get();

        $lost_types = [
            'PSA',
            'シングル',
            'BOX',
            'パック',
            'ポイント',
        ];
        $plans = Plan::orderBy('amount', 'ASC')->get();
        $video_names = Gacha_video::where('gacha_id', 0)->pluck('level')->toArray();
        
        return inertia('Admin/Gacha/Edit', compact('gacha', 'product_last', 'products', 'productsLostSetting', 'videos', 'ranks', 'lost_types', 'video_names', 'plans'));
    }

    public function update(Request $request) { 
        $rules = [
            'point' => 'required',
            'consume_point' => 'required',
            'count_card' => 'required|numeric',
            'count' => 'required|numeric',
            'lost_product_type' => '',
            'thumbnail' => 'required|image|max:4096',
            'image' => 'required|image',
            'category_id' => 'required',
            'spin_limit' => 'required|numeric',
            'rank_limit' => 'required|numeric',
            'plan_limit' => 'required|numeric',
            // 'need_line' => 'required',
        ];
        
        if (!$request->thumbnail) {
            $rules['thumbnail'] = '';
        } 
        if (!$request->image) {
            $rules['image'] = ''; 
        }
        $validatored = $request->validate($rules);

        $data = [
            'title' => $request->title,
            'point' => $request->point,
            'consume_point' => $request->consume_point,
            'count_card' => $request->count_card,
            'count' => $request->count,
            'lost_product_type' => $request->lost_product_type,
            'category_id' => $request->category_id,
            'spin_limit' => $request->spin_limit,
            'rank_limit' => $request->rank_limit,
            'plan_limit' => $request->plan_limit,
            // 'need_line' => $request->need_line,
        ];

        if ($request->start_time) 
        $data['start_time'] = date('Y-m-d H:i', strtotime($request->start_time));
        else $data['start_time'] = null;
        
        if ($request->end_time)
        $data['end_time'] = date('Y-m-d H:i', strtotime($request->end_time));
        else $data['end_time'] = null;
    
        if ($request->thumbnail) {
            $thumbnail = saveImage('images/gacha/thumbnail', $request->file('thumbnail'), false);
            $data['thumbnail'] = $thumbnail;
        }
        if ($request->image) {
            $image = saveImage('images/gacha', $request->file('image'), false);
            $data['image'] = $image;
        }
        $gacha = Gacha::find($request->id);

        if (auth()->user()->getType()=="staff" && $gacha->status!=0) {
            $text = "権限がありません！";
            return inertia('NoProduct', compact('text'));
        }

        $gacha->update($data);

        Gacha_video::where('gacha_id', $gacha->id)->delete();
        if (isset($request->videos)) foreach($request->videos as $video) {
            Gacha_video::create([
                'level' => $video['level'],
                'gacha_id' => $gacha->id,
                'point' => $video['point'],
                'file' => null
            ]);
        }
        
        // lost products
        Gacha_lost_product::where('gacha_id', $gacha->id)->delete();
        if($request->lostProducts) {
            foreach($request->lostProducts as $item) {
                if ($item['key']) {
                    $point = 0;
                    if ($item['point']) { $point = $item['point']; }
                    $count = 0;
                    if ($item['count']) { $count = $item['count']; };
                    $data = ['gacha_id'=>$gacha->id, 'point'=>$point, 'count'=>$count];
                    Gacha_lost_product::create($data);
                }
            }
        }
        // lost products end

        return redirect()->back()->with('message', '保存しました！')->with('title', 'ガチャ 編集')->with('type', 'dialog');
    }

    public function sorting(Request $request) {
        $cat_id = $request->cat_id ? $request->cat_id : getCategories()[0]->id;
        $GachaObj = Gacha::where('category_id', $cat_id);
        if (auth()->user()->getType()=="staff") {
            $GachaObj->where('status', 0);
        }
        $gachas = $GachaObj->orderBy('order_level', 'DESC')->orderBy('id', 'DESC')->get();
        $shuffle_mode = getOption('shuffle_mode') == '1';

        $gachas = GachaListResource::collection($gachas);
        return inertia('Admin/Gacha/Sorting', compact('gachas', 'shuffle_mode'));
    }

    public function sorting_store(Request $request) {
        $data = $request->all();
        $order_level = 1;
        $data['gachas'] = array_reverse($data['gachas']);
        foreach($data['gachas'] as $key=>$item) {
            Gacha::where('id', $item['id'])->update([
                'order_level'=>$order_level + ($item['fixed'] ? 1000000 : 0)
            ]);
            $order_level += 1;
        }
        return redirect()->back()->with('message', '保存しました！')->with('title', 'ガチャ編集')->with('type', 'dialog');
    }

    public function product_last_create(Request $request) {
        $rules = [
            'last_name' => 'required',
            'last_point' => 'required|numeric',
            // 'last_rare' => 'required',
            'last_image' => 'required|image|max:4096',
            'gacha_id' => 'required',
        ];
        if ($request->is_update==1) {
            if(!$request->last_image){
                $rules['last_image'] = '';
            }
        }

        $validatored = $request->validate($rules); 
        
        $data = [
            'name' => $request->last_name,
            'point' => $request->last_point,
            'rare' => $request->last_rare,
            'gacha_id' => $request->gacha_id,
            'is_last' => 1,
        ];
        if($request->last_image){
            $image = saveImage('images/products', $request->file('last_image'), false);
            $data['image'] = $image;
        }

        if ($request->is_update==1) { 
            $products = Product::where('is_last', 1)->where('is_lost_product', 0)->where('gacha_id', $request->gacha_id)->get();
            if (count($products)>0) {
                $products[0]->update($data);
            } else {
                return redirect()->back()->with('message', '失敗しました！')->with('title', 'ガチャ 編集')->with('type', 'dialog');
            }
        } else {
            Product::create($data);
        }
        return redirect()->back()->with('message', '保存しました！')->with('title', 'ガチャ 編集')->with('type', 'dialog');
    }

    public function product_last_destroy($id) {
        Product::where("id", $id)->delete();
        return redirect()->back()->with('message', '削除しました！')->with('title', '編集')->with('type', 'dialog');
    }

    public function product_create(Request $request) {
        $rules = [
            'last_name' => 'required',
            'last_point' => 'required|numeric',
            'last_rare' => 'required',
            'last_image' => 'required|image|max:4096',
            'gacha_id' => 'required',
            'rank' => 'required',
        ];
        if ($request->is_update==1) {
            if(!$request->last_image){
                $rules['last_image'] = '';
            }
        }

        $validatored = $request->validate($rules);
        
        $data = [
            'name' => $request->last_name,
            'point' => $request->last_point,
            'rare' => $request->last_rare,
            'gacha_id' => $request->gacha_id,
            'marks' => $request->last_marks,
            'order' => $request->last_order,
            'lost_type' => $request->last_lost_type,
            'is_last' => 0,
            'rank' => $request->rank,
            'category_id' => $request->category_id,
        ];
        if($request->last_image){
            $image = saveImage('images/products', $request->file('last_image'), false);
            $data['image'] = $image;
        }
        
        if ($request->is_update==1) {
            $product = Product::where('id', $request->last_id);
            $result = $product->update($data);
            if (!$result) {
                return redirect()->back()->with('message', '失敗しました！')->with('title', 'ガチャ 編集')->with('type', 'dialog');        
            }
        } else {
            Product::create($data);
        }
        return redirect()->back()->with('message', '保存しました！')->with('title', 'ガチャ 編集')->with('type', 'dialog');
    }

    public function to_public (Request $request) {
        $gacha_id = $request->gacha_id;
        if (!$gacha_id) {
            return redirect()->back();
        }

        $gacha = Gacha::find($gacha_id);
        if ( !$gacha ) {
            return redirect()->back();
        }
       

        $gacha->status = $request->to_status;
        $gacha->save();

        $string = "非公開にしました！";
        if ($request->to_status) {
            $string = "公開にしました！";
        }
        return redirect()->back()->with('message', $string)->with('title', 'ガチャ 編集')->with('type', 'dialog');
    }

    public function gacha_limit (Request $request) {
        $gacha_id = $request->gacha_id;
        if (!$gacha_id) {
            return redirect()->back();
        }

        $gacha = Gacha::find($gacha_id);
        if ( !$gacha ) {
            return redirect()->back();
        }
        
        $gacha->gacha_limit = $request->to_status;
        $gacha->save();

        $string = "1日1回制限設定をキャンセルしました。";
        if ($request->to_status) {
            $string = "1日1回制限設定を完了しました。";
        }
        return redirect()->back()->with('message', $string)->with('type', 'dialog');
    }

    public function destroy($id) {
        Product::where('gacha_id', $id)->where('is_lost_product', 0)->delete();
        Gacha_video::where('gacha_id', $id)->delete();
        Gacha::where('id', $id)->delete();
        Gacha_lost_product::where('gacha_id', $id)->delete();
        // Favorite::where('product_id', $id)->delete();
        // Product::where("id", $id)->where('is_lost_product', 2)->delete();
        return redirect()->back()->with('message', '削除しました！')->with('title', 'ガチャ')->with('type', 'dialog');
    }

    public function upload_csv(Request $request) {
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt',
            'gacha_id' => 'required|exists:gachas,id',
            'images.*' => 'nullable|image|max:4096'
        ]);

        $gacha = Gacha::find($request->gacha_id);
        if (!$gacha) {
            return redirect()->back()->with('message', 'ガチャが見つかりません。')->with('type', 'dialog_error');
        }

        DB::beginTransaction();
        try {
            // Create a map of uploaded image filenames to their paths
            $imageMap = [];
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $image) {
                    $originalName = $image->getClientOriginalName();
                    $newPath = saveImage('images/products', $image, false);
                    $imageMap[$originalName] = $newPath;
                }
            }

            $products = array_map('str_getcsv', file($request->file('csv')));
            for ($i = 0; $i < count($products); $i++) {
                $product = $products[$i];
                if (!is_numeric($product[0]) && trim($product[0]) != '') continue;
                
                if (trim($product[0]) == '') {
                    $id = null;
                    $update = false;
                } else {
                    $id = intval($product[0]);
                    $update = true;
                }
                
                if ($update && !Product::find($id)) {
                    DB::rollBack();
                    return redirect()->back()->with('message', '商品が見つかりません。')->with('title', 'カード登録失敗')->with('message_id', Str::random(9))->with('type', 'dialog');
                }

                $productData = [
                    'name' => $product[1],
                    'point' => $product[2],
                    'lost_type' => $product[3],
                    'rare' => $product[4],
                    'rank' => $product[5],
                    'marks' => $product[6],
                    'category_id' => $product[7],
                    'order' => $product[8],
                    'gacha_id' => $gacha->id,
                    'is_last' => 0,
                    'is_lost_product' => 0,
                ];

                // Handle image path
                if (isset($product[9]) && !empty($product[9])) {
                    $imagePath = trim($product[9]);
                    
                    // If it's a local path from the uploaded files
                    if (isset($imageMap[basename($imagePath)])) {
                        $productData['image'] = $imageMap[basename($imagePath)];
                    }
                    // If it's a full URL
                    else if (filter_var($imagePath, FILTER_VALIDATE_URL)) {
                        try {
                            $imageContent = file_get_contents($imagePath);
                            if ($imageContent !== false) {
                                // Generate a random filename with original extension
                                $extension = pathinfo(parse_url($imagePath, PHP_URL_PATH), PATHINFO_EXTENSION);
                                $extension = $extension ?: 'jpg'; // Default to jpg if no extension found
                                $randomName = Str::random(32) . '.' . $extension;
                                $newPath = 'images/products/' . $randomName;
                                
                                if (file_put_contents(public_path($newPath), $imageContent)) {
                                    $productData['image'] = $randomName;
                                }
                            }
                        } catch (\Exception $e) {
                            // Log the error but continue processing other products
                            \Log::error('Failed to download image from URL: ' . $imagePath . ' - ' . $e->getMessage());
                        }
                    }
                    // If it's a local server path
                    else if (str_starts_with($imagePath, '/')) {
                        $productData['image'] = ltrim($imagePath, '/');
                    }
                }

                if ($update) {
                    Product::where('id', $id)->update($productData);
                } else {
                    Product::create($productData);
                }
            }
            DB::commit();
            return redirect()->back()->with('message', 'csvによる一括登録が成功しました。')->with('title', 'カード登録成功')->with('message_id', Str::random(9))->with('type', 'dialog');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', 'csvファイルを再確認してください。'.$e->getMessage())->with('title', 'カード登録失敗')->with('message_id', Str::random(9))->with('type', 'dialog');
        }
    }
}
