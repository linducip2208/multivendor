<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryManRating;
use App\Models\User;
use Illuminate\Http\Request;

class DeliveryRatingAdminController extends Controller
{
    public function index()
    {
        $ratings = DeliveryManRating::with(['deliveryMan', 'customer', 'order'])->latest()->paginate(20);
        return view('admin.delivery.ratings', compact('ratings'));
    }

    public function deliveryManReport(User $user)
    {
        if ($user->role !== 'delivery') {
            abort(404);
        }

        $ratings = DeliveryManRating::where('delivery_man_id', $user->id)
            ->with(['customer', 'order'])
            ->latest()
            ->paginate(20);

        $avgRating = $ratings->pluck('rating')->avg() ?? 0;
        $totalRatings = DeliveryManRating::where('delivery_man_id', $user->id)->count();
        $completedDeliveries = \App\Models\Order::where('delivery_man_id', $user->id)
            ->where('order_status', 'delivered')
            ->count();

        return view('admin.delivery.rating-report', compact('user', 'ratings', 'avgRating', 'totalRatings', 'completedDeliveries'));
    }
}
