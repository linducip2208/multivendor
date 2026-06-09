@extends('layouts.admin')
@section('title', 'Bahasa')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-language me-2 text-info"></i> Bahasa</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form action="{{ route('admin.language.update') }}" method="POST">@csrf @method('PUT')
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Key</th><th>Indonesia</th><th>English</th></tr></thead><tbody>
@php $keys = ['Dashboard','Products','Categories','Brands','Cart','Checkout','Orders','Login','Register','Logout','Vendors','Customers','Coupons','Flash Deals','Settings','Reports','Profile','Save','Cancel','Delete','Edit','Create','Search','Filter','Status','Actions','Total','Price','Stock','Quantity','Add to Cart','Buy Now','Wishlist','Compare','Support Tickets','Language','Currency']; @endphp
@foreach($keys as $k)
<tr><td class="fw-medium">{{ $k }}</td>
<td><input type="text" name="id[{{ $k }}]" class="form-control form-control-sm" value="{{ old('id.'.$k, __('messages.'.$k, [], 'id') ?: $k) }}"></td>
<td><input type="text" name="en[{{ $k }}]" class="form-control form-control-sm" value="{{ old('en.'.$k, __('messages.'.$k, [], 'en') ?: $k) }}"></td>
</tr>
@endforeach
</tbody></table></div>
<button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Semua</button>
</form></div></div>
@endsection
