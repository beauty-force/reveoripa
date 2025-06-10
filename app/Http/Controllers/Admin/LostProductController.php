<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\Gacha_lost_product;
use App\Http\Resources\ProductListResource;
use Str;

class LostProductController extends Controller
{
    public function index(Request $request) {
        $cat_id = $request->cat_id ? $request->cat_id : getCategories()[0]->id;
        $page_size = 20;
        $rare = $request->rare ? $request->rare : "";
        $name = $request->name ? $request->name : "";
        $min = $request->min ? intval($request->min) : "";
        $max = $request->max ? intval($request->max) : "";
        $page = $request->page ? intval($request->page) : 1;
        
        $products = Product::where('is_lost_product', 1)->where('category_id', $cat_id);
        if ($rare != "") $products = $products->where('rare', $rare);
        if ($min != "") $products = $products->where('point', '>=', $min);
        if ($max != "") $products = $products->where('point', '<=', $max);
        if ($name != "") $products = $products->where('name', 'like', '%'.$name.'%');

        $total = $products->count();
        $total = ceil($total / $page_size);
        if ($total == 0) $total = 1;
        if ($page > $total) $page = $total;

        $products = $products->orderBy('point', 'DESC')->orderBy('id', 'ASC')
        ->offset(($page - 1) * $page_size)->limit($page_size)->get();

        $points = Gacha_lost_product::where('count', '>', 0)->orderBy('point')->select('point')->get();
        $point = 0; $products_status = [];
        foreach($points as $point1) {
            if ($point1->point!=$point) {
                $point = $point1->point;
                $gacha_products_count = Gacha_lost_product::leftJoin('gachas', function($join) { $join->on('gachas.id', '=', 'gacha_lost_products.gacha_id'); })
                ->where('gacha_lost_products.point', $point)
                ->where('gachas.category_id', $cat_id)
                ->sum('gacha_lost_products.count');
                $products_lost_count = Product::where('point', $point)->where('is_lost_product', 1)->where('category_id', $cat_id)->where('marks', '>', 0)->sum('marks');
                $arr['point'] = $point;
                $arr['gacha_products_count'] = $gacha_products_count;
                $arr['products_lost_count'] = $products_lost_count;
                array_push( $products_status , $arr);
            }  
        }
        $products = ProductListResource::collection($products);
        // return $products_status;
        $search_cond = [
            "cat_id" => $cat_id,
            "rare" => $rare,
            "name" => $name,
            "min" => $min,
            "max" => $max,
            "page" => $page,
        ];

        $lost_types = [
            'PSA',
            'シングル',
            'BOX',
            'パック',
            'ポイント',
        ];

        return inertia('Admin/LostProduct/Index', compact('products', 'products_status', 'search_cond', 'total', 'lost_types'));
    }
    
    public function create(Request $request) {
        $rules = [
            'last_name' => 'required',
            'last_point' => 'required|numeric',
            'last_rare' => 'required',
            'last_marks' => 'required|numeric',
            'last_image' => 'required|image|max:2048',
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
            'marks' => $request->last_marks,
            'lost_type' => $request->last_lost_type,
            'category_id' => $request->category_id,
            'is_lost_product' => 1,
        ];
        
        if($request->last_image){
            $image = saveImage('images/products', $request->file('last_image'), false);
            $data['image'] = $image;
        }
        
        if ($request->is_update==1) {
            $product = Product::find($request->last_id);
            $product->update($data);
        } else {
            Product::create($data);
        }

        return redirect()->back()->with('message', '保存しました！')->with('title', 'カード 編集')->with('type', 'dialog');;
    }
}
