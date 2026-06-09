<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index() { return view('storefront.profile.index'); }
    public function update(Request $request) {
        $v = $request->validate(['name'=>'required|string|max:255','phone'=>'nullable|string|max:20','password'=>'nullable|min:6|confirmed']);
        auth()->user()->update(['name'=>$v['name'],'phone'=>$v['phone']]);
        if($v['password']) auth()->user()->update(['password'=>\Illuminate\Support\Facades\Hash::make($v['password'])]);
        return back()->with('success','Profil diperbarui.');
    }
    public function addressStore(Request $request) {
        $v = $request->validate(['label'=>'required','receiver_name'=>'required','receiver_phone'=>'required','address'=>'required','city'=>'required','province'=>'required','postal_code'=>'nullable']);
        $v['customer_id'] = auth()->id();
        if($request->is_default) \App\Models\CustomerAddress::where('customer_id',auth()->id())->update(['is_default'=>false]);
        $v['is_default'] = $request->boolean('is_default');
        \App\Models\CustomerAddress::create($v);
        return back()->with('success','Alamat ditambahkan.');
    }
    public function addressDestroy(\App\Models\CustomerAddress $address) {
        if($address->customer_id !== auth()->id()) abort(403);
        $address->delete(); return back()->with('success','Alamat dihapus.');
    }
}
