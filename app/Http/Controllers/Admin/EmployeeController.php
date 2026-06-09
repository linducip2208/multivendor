<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index() { $employees = User::whereIn('role',['admin','employee'])->latest()->paginate(15); return view('admin.employees.index',compact('employees')); }
    public function create() { return view('admin.employees.create'); }
    public function store(Request $request) {
        $v = $request->validate(['name'=>'required','email'=>'required|email|unique:users','password'=>'required|min:6','role'=>'required|in:admin,employee']);
        User::create(['name'=>$v['name'],'email'=>$v['email'],'password'=>Hash::make($v['password']),'role'=>$v['role'],'status'=>'active']);
        return redirect()->route('admin.employees.index')->with('success','Employee ditambahkan.');
    }
    public function edit(User $employee) { return view('admin.employees.edit',compact('employee')); }
    public function update(Request $request, User $employee) {
        $v = $request->validate(['name'=>'required','email'=>'required|email|unique:users,email,'.$employee->id,'password'=>'nullable|min:6','role'=>'required|in:admin,employee']);
        $employee->update(['name'=>$v['name'],'email'=>$v['email'],'role'=>$v['role']]);
        if($v['password']) $employee->update(['password'=>Hash::make($v['password'])]);
        return redirect()->route('admin.employees.index')->with('success','Employee diperbarui.');
    }
    public function destroy(User $employee) { $employee->delete(); return back()->with('success','Employee dihapus.'); }
}
