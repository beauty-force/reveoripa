<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\Product;
use App\Models\Favorite;
use App\Http\Resources\ProductListResource;
use Str;

class DpController extends Controller
{
    public function index(Request $request) {
        $cat_id = 1;
        if ($request->cat_id) {
            $cat_id = $request->cat_id;
        }
        $cats = getCategories();
        $categories = [];
        
        foreach($cats as $cat) {
            if (!str_contains($cat->title, '無限確率')) {
                $categories[] = $cat;
            }
        }
        
        $category_share = [
            'cat_id' => $cat_id,
            'categories' => $categories,
            'cat_route_appendix' => "?cat_id=". $cat_id
        ];

        $page_size = 20;
        $name = $request->name ? $request->name : "";
        $min = $request->min ? intval($request->min) : "";
        $max = $request->max ? intval($request->max) : "";
        $page = $request->page ? intval($request->page) : 1;
        
        $products = Product::where('is_lost_product', 2)->where('category_id', $cat_id);
        if ($min != "") $products = $products->where('dp', '>=', $min);
        if ($max != "") $products = $products->where('dp', '<=', $max);
        if ($name != "") $products = $products->where('name', 'like', '%'.$name.'%');

        $total = $products->count();
        $total = ceil($total / $page_size);
        if ($total == 0) $total = 1;
        if ($page > $total) $page = $total;

        $products = $products->orderBy('dp', 'DESC')->orderBy('id', 'ASC')
        ->offset(($page - 1) * $page_size)->limit($page_size)->get();

        $products = ProductListResource::collection($products);

        $search_cond = [
            "cat_id" => $cat_id,
            "name" => $name,
            "min" => $min,
            "max" => $max,
            "page" => $page,
        ];
        // return $products; 
        return inertia("Admin/Dp/Index", compact('products', 'search_cond', 'total', 'category_share'));
    }

    public function create(Request $request) {
        $cat_id = 1;
        if ($request->cat_id) {
            $cat_id = $request->cat_id;
        }
        $cats = getCategories();
        $categories = [];
        
        foreach($cats as $cat) {
            if (!str_contains($cat->title, '無限確率')) {
                $categories[] = $cat;
            }
        }
        
        $category_share = [
            'cat_id' => $cat_id,
            'categories' => $categories,
            'cat_route_appendix' => "?cat_id=". $cat_id
        ];

        $productStatusTxt = getProductStatusTxt();
        return inertia("Admin/Dp/Create", compact('productStatusTxt', 'category_share'));
    }


    public function store(Request $request) {
        $rules = [
            'name' => 'required',
            'dp' => 'required|numeric',
            'marks' => 'required|numeric',
            'image' => 'required|image|max:2048',
            'status_product' => 'required',
        ];
        $validatored = $request->validate($rules);

        $image = saveImage('images/products', $request->file('image'), false);
        
        $data = [
            'name' => $request->name,
            'dp' => $request->dp,
            'marks' => $request->marks,
            'image' => $image,
            'category_id' => $request->category_id,
            'rare' => $request->rare,
            'product_type' => $request->product_type,
            'status_product' => $request->status_product,
            'is_lost_product' => 2,
        ];
        Product::create($data);
        return redirect(combineRoute(route('admin.dp'), $request->category_id))->with('message', '追加しました！')->with('title', '交換アイテム')->with('type', 'dialog');
    }

    public function edit($id, Request $request) {
        $cat_id = 1;
        if ($request->cat_id) {
            $cat_id = $request->cat_id;
        }
        $cats = getCategories();
        $categories = [];
        
        foreach($cats as $cat) {
            if (!str_contains($cat->title, '無限確率')) {
                $categories[] = $cat;
            }
        }
        
        $product = Product::where('is_lost_product', 2)->where('id', $id)->first();
        
        $category_share = [
            'cat_id' => $cat_id,
            'categories' => $categories,
            'cat_route_appendix' => "?cat_id=". $cat_id
        ];

        if ($product) {
            $product->image = getProductImageUrl($product->image);
            $productStatusTxt = getProductStatusTxt();
            return inertia('Admin/Dp/Edit', compact('product', 'productStatusTxt', 'category_share'));
        } 
        return redirect()->route('admin.dp');
    }

    public function update(Request $request) {
        $rules = [
            'id' => 'required',
            'name' => 'required',
            'dp' => 'required|numeric',
            'marks' => 'required|numeric',
            'image' => 'required|image|max:2048',
            'status_product' => 'required',
        ];
        if (!$request->image) {
            $rules['image'] = '';
        }
        $validatored = $request->validate($rules);

        $data = [
            'name' => $request->name,
            'dp' => $request->dp,
            'marks' => $request->marks,
            'category_id' => $request->category_id,
            'rare' => $request->rare,
            'product_type' => $request->product_type,
            'status_product' => $request->status_product,
            'is_lost_product' => 2,
        ];
        if ($request->image) {
            $image = saveImage('images/products', $request->file('image'), false);
            $data['image'] = $image;
        }

        $product = Product::find($request->id);
        $product->update($data);

        return redirect(combineRoute(route('admin.dp'), $request->category_id))->with('message', '保存しました！')->with('title', '交換アイテム')->with('type', 'dialog');
    }

    public function destroy($id = null) {
        Favorite::where('product_id', $id)->delete();
        Product::where("id", $id)->where('is_lost_product', 2)->delete();
        return redirect()->back()->with('message', '削除しました！')->with('title', '交換アイテム')->with('type', 'dialog');
    }
}
