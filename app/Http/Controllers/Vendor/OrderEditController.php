<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;

class OrderEditController extends Controller
{
    public function edit(Order $order) {
        $shop = auth('vendor')->user()->shop;
        if($order->shop_id !== $shop->id) abort(403);
        $products = Product::where('shop_id',$shop->id)->where('status','approved')->get();
        $order->load('items.product');
        return view('vendor.order-edit.index',compact('order','products'));
    }
    public function update(Request $request, Order $order) {
        $shop = auth('vendor')->user()->shop;
        if($order->shop_id !== $shop->id) abort(403);
        $order->items()->delete();
        $subTotal = 0;
        foreach(($request->items??[]) as $item) {
            $product = Product::find($item['product_id']);
            if($product && $product->shop_id === $shop->id) {
                $st = $product->price * (int)$item['quantity'];
                OrderItem::create(['order_id'=>$order->id,'product_id'=>$item['product_id'],'quantity'=>(int)$item['quantity'],'price'=>$product->price,'tax'=>0,'discount'=>0,'sub_total'=>$st]);
                $subTotal += $st;
            }
        }
        $order->update(['sub_total'=>$subTotal,'total'=>$subTotal + $order->shipping_cost - $order->discount]);
        $order->statusHistory()->create(['status'=>'edited','changed_by'=>auth('vendor')->id(),'note'=>'Pesanan diedit']);
        return redirect()->route('vendor.orders.show',$order)->with('success','Pesanan diperbarui.');
    }
}
