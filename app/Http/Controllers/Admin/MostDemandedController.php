<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;

class MostDemandedController extends Controller
{
    public function index() {
        $products = Product::where('status','approved')->withCount(['orderItems as total_ordered'=>fn($q)=>$q->whereHas('order',fn($o)=>$o->where('order_status','!=','canceled'))])->orderByDesc('total_ordered')->take(50)->with('shop')->paginate(25);
        return view('admin.most-demanded.index',compact('products'));
    }
}
