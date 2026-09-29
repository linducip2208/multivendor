@extends('layouts.admin')
@section('title', 'Laporan Penjualan Vendor')
@section('content')
<h4 class="fw-bold mb-3"><x-admin.icon name="store" :size="16" class="me-2 text-success" /> Penjualan per Vendor</h4>
<x-admin.card :padding="false"><div class="table-responsive"><table class="table table-hover mb-0"><thead><tr><th>Vendor/Toko</th><th>Produk</th><th>Pesanan</th><th>Pendapatan</th><th>Komisi Admin</th><th>Vendor Diterima</th></tr></thead><tbody>
@foreach(\App\Models\Shop::where('status','active')->withCount('products')->withSum('orders as revenue','total')->withCount('orders')->get() as $s)
<tr><td class="fw-medium">{{ $s->name }}</td><td>{{ $s->products_count }}</td><td>{{ $s->orders_count }}</td><td class="fw-bold">Rp {{ number_format($s->revenue ?? 0,0,',','.') }}</td><td class="text-danger">Rp {{ number_format(\App\Models\Transaction::where('shop_id',$s->id)->where('status','success')->sum('admin_commission'),0,',','.') }}</td><td class="text-success fw-bold">Rp {{ number_format(\App\Models\Transaction::where('shop_id',$s->id)->where('status','success')->sum('vendor_amount'),0,',','.') }}</td></tr>
@endforeach
</tbody></table></div></x-admin.card>
@endsection
