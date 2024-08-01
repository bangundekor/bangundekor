<?php

namespace App\Http\Controllers;

use Exception;
use Midtrans\Snap;
use App\Models\Cart;
use Midtrans\Config;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Requests\CheckoutRequest;
use App\Models\TransactionItem;
use App\Models\Category;

class FrontendController extends Controller
{
    public function index(Request $request)
    {
       $products = Product::with(['galleries'])->latest()->get();

        return view('pages.frontend.index', compact('products'));

        //return view('pages.frontend.index', compact('categories', 'products'));


        //$products = Product::with(['galleries'])->latest()->get();

        //return view('pages.frontend.index', compact('products'));
    }

    public function browseRoom(Request $request)
    {
        $categories = Category::all();

        $categoryId = $request->get('category_id');
        $products = Product::all();

        if ($categoryId) {
            $products = Product::where('category_id', $categoryId)->get();
        }

        return view('pages.frontend.browse-room', compact('categories', 'products'));
    }


   // public function details(Request $request, $category, $slug)
    //{
      //  $product = Product::with(['galleries'])
        //->where('category_id', $category) // Menambahkan filter kategori
        //->where('slug', $slug)
        //->firstOrFail();

        //return view('pages.frontend.details', compact('product','recommendations'));
    //}

    public function details(Request $request, $slug)
    {
        $product = Product::with(['galleries'])->where('slug', $slug)->firstOrFail();
        $recommendations = Product::with(['galleries'])->inRandomOrder()->limit(4)->get();

        return view('pages.frontend.details', compact('product','recommendations'));
    }

    public function cartAdd(Request $request, $id)
    {
        Cart::create([
            'users_id' => Auth::user()->id,
            'products_id' => $id,
        ]);

        return redirect('cart');
    }

    public function cartDelete(Request $request, $id)
    {
        $item = Cart::findOrFail($id);

        $item->delete();

        return redirect('cart');
    }

    public function cart(Request $request)
    {
        $carts = Cart::with(['product.galleries'])->where('users_id', Auth::user()->id)->get();

        return view('pages.frontend.cart', compact('carts'));
    }

    public function checkout(CheckoutRequest $request)
    {
        $data = $request->all();

        // Get Carts data
        $carts = Cart::with(['product'])->where('users_id', Auth::user()->id)->get();

        // Add to Transaction data
        $data['users_id'] = Auth::user()->id;
        $data['total_price'] = $carts->sum('product.price');
    
        // Create Transaction
        $transaction = Transaction::create($data);

        // Create Transaction item
        foreach($carts as $cart) {
            $items[] = TransactionItem::create([
                'transactions_id' => $transaction->id,
                'users_id' => $cart->users_id,
                'products_id' => $cart->products_id,
            ]);
        }
        
        // Delete cart after transaction
        Cart::where('users_id', Auth::user()->id)->delete();

        // Konfigurasi midtrans
        Config::$serverKey = config('services.midtrans.serverKey');
        Config::$isProduction = config('services.midtrans.isProduction');
        Config::$isSanitized = config('services.midtrans.isSanitized');
        Config::$is3ds = config('services.midtrans.is3ds');

        // Setup midtrans variable
        $midtrans = array(
            'transaction_details' => array(
                'order_id' =>  'LX-' . $transaction->id,
                'gross_amount' => (int) $transaction->total_price,
            ),
            'customer_details' => array(
                'first_name'    => $transaction->name,
                'email'         => $transaction->email
            ),
            'enabled_payments' => array('gopay','bank_transfer'),
            'vtweb' => array()
        );

        try {
            // Ambil halaman payment midtrans
            $paymentUrl = Snap::createTransaction($midtrans)->redirect_url;

            $transaction->payment_url = $paymentUrl;
            $transaction->save();

            // Redirect ke halaman midtrans
            return redirect($paymentUrl);
        }
        catch (Exception $e) {
            return $e;
        }

    }

    public function success(Request $request)
    {
        return view('pages.frontend.success');
    }
}
