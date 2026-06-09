@extends('layouts.admin')
@section('title', 'Custom Role')
@section('content')
<h4 class="fw-bold mb-3"><i class="fas fa-user-tag me-2 text-primary"></i> Custom Role (Employee)</h4>
<div class="card border-0 rounded-4 shadow-sm"><div class="card-body p-4">
<form action="{{ route('admin.roles.update') }}" method="POST">@csrf @method('PUT')
<div class="table-responsive"><table class="table table-bordered"><thead><tr><th>Modul</th><th>Lihat</th><th>Tambah</th><th>Edit</th><th>Hapus</th></tr></thead><tbody>
@php $modules = ['vendors'=>'Vendor','products'=>'Produk','categories'=>'Kategori','brands'=>'Brand','orders'=>'Pesanan','coupons'=>'Kupon','flashdeals'=>'Flash Deal','blog'=>'Blog','banners'=>'Banner','customers'=>'Pelanggan','delivery-men'=>'Kurir','reports'=>'Laporan','settings'=>'Pengaturan','providers'=>'Integrasi']; @endphp
@foreach($modules as $key=>$label)
<tr><td class="fw-medium">{{ $label }}</td>
@foreach(['view','create','edit','delete'] as $perm)
<td><input type="checkbox" name="roles[{{ $key }}][{{ $perm }}]" value="1" {{ \App\Models\SystemSetting::get('role_'.$key.'_'.$perm) ? 'checked' : '' }}></td>
@endforeach
</tr>
@endforeach
</tbody></table></div>
<button class="btn btn-primary"><i class="fas fa-save me-2"></i>Simpan Role</button>
</form></div></div>
@endsection
