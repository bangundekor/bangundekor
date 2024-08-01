<?php

namespace App\Http\Controllers\API;

use Xendit\Xendit;
use App\Models\Transaction;
use App\Http\Controllers\Controller;

class XenditCallback extends Controller
{
public function callback()
    {
        // Set konfigurasi Xendit
        Xendit::setApiKey(config('services.xendit.api_key'));

        // Ambil payload dari request
        $payload = request()->all();

        // Buat objek Xendit Notification
        $notification = Xendit\Invoice::retrieve($payload['external_id']);

        // Assign ke variable untuk memudahkan coding
        $status = $notification['status'];
        $order_id = $notification['external_id']; // atau gunakan field yang sesuai dari Xendit

        // Get Transaction ID
        $order = explode('-', $order_id);

        // Cari transaksi berdasarkan ID
        $transaction = Transaction::findOrFail($order[1]);

        // Handle notification   
 status Xendit
        if ($status === 'PAID') {
            $transaction->status = 'SUCCESS';
        } elseif ($status === 'FAILED') {
            $transaction->status = 'FAILED';
        } elseif ($status === 'PENDING') {
            $transaction->status = 'PENDING';
        } else {
            $transaction->status = 'UNKNOWN';
        }

        // Simpan transaksi
        $transaction->save();

        // Return response
        return response()->json([
            'meta' => [
                'code' => 200,
                'message' => 'Xendit Notification Success'
            ]
        ]);
    }
}
