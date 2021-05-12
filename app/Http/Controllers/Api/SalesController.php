<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\ResourceController;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\Coupon;
use App\Models\SaleItems;
use App\Models\SalesItem;
use App\Mail\PurchaseSuccessful;
use App\Models\Location;
use App\Mail\NewSale;
use Illuminate\Support\Facades\Crypt;
use Mail;
use Auth;
use Facade\Ignition\QueryRecorder\Query;
use PDF;
use Carbon\Carbon;

class SalesController extends ResourceController
{
    protected $resourceModel = 'App\Models\Sale';
    protected $paginate = false;
    protected $perPage = 12;

    protected function afterIndex($query)
    {
        return $query->with('user', 'items');
    }

    protected function byTerm($query, $term)
    {
        return $query->where(function ($query) use ($term) {
            return $query->where('name', 'LIKE', "%$term%")
                ->orWhere('email', 'LIKE', "%$term%");
        });
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'user_id' => '',
            'delivery_address' => 'required|string|max:255',
            'delivery_date' => 'required',
            'amout' => '',
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:255',
            'email' => 'required|string|max:255',
            'payment_type' => 'required|string|max:255',
            'order_note' => 'max:255',
            'location_id' => 'required'
        ]);

        $total =  collect($request->cart)->sum('total');
        $location = Location::find($request->location_id);
        $subtotal =  $total < $location->min ? $location->min : $total;
        
        $sale = new Sale();
        $sale->name = $request->name;
        $sale->phone = $request->phone;
        $sale->delivery_address = $request->delivery_address . ' ' . $request->address_note . ' ' . $request->neighborhood;
        $sale->email = $request->email;
        $sale->order_note = $request->order_note;
        $sale->payment_type = $request->payment_type;
        $sale->payment_status = 'PENDING';
        $sale->location_id = $request->location_id;
        $sale->delivery_date = $request->delivery_date;
        $sale->coupon_code = $request->coupon_code;
        $sale->coupon_amount = $request->coupon_amount;
        $sale->total_discount = $request->total_discount; 
        $sale->subtotal = $subtotal;
        $sale->total = $subtotal - $request->total_discount;
        $sale->user_id = auth()->user()->id;
        $sale->save();


        $this->storeSaleItems($request->cart, $sale->id);

        $sale->load('items');

        // marco el cupon como utilizado
        if ($sale->coupon_code != '') {
            $coupon = Coupon::where('email', Auth::user()->email)
                ->where('code', strtoupper($sale->coupon_code))
                ->first();
            if ($coupon) {
                $coupon->used = 1;
                $coupon->save();
            }
        }

        $sale->mpLink = url('/api/payments/generar-pago/' . Crypt::encryptString($sale->id));

        Mail::to($request->email)->send(new PurchaseSuccessful($sale));

       
        Mail::to(config('mail.to.hola'))->send(new NewSale($sale));
        
        // if (Mail::failures()) {
        //     return response()->json(['status' => false]);
        // }

        return response()->json(['status' => true, 'hash' => Crypt::encryptString($sale->id),]);
    }

    private function storeSaleItems($cart, $sale_id)
    {
        foreach ($cart as $item) {

            $product = new SaleItems();
            $product->sale_id = $sale_id;
            $product->product_id = $item['product']['id'];
            $product->description = $item['product']['title'];
            $product->qty = $item['qty'];
            $product->price = $item['product']['price'];
            $product->discount = $item['product']['discount'];
            if ($item['product']['sale_type'] === 'Peso') {

                $variant = collect($item['product']['variants'])->first(function ($it) use ($item) {
                    return $it['id'] == $item['variant'];
                });
                $product->price = $variant['price'];
                $product->variant_id = $variant['id'];
                $product->variant_description = $item['product']['title'] . ' ' . $this->getWeight($variant['weight']);
            }
            $product->total = $item['total'];
            $product->save();
        }
    }

    public function changePaymentStatus(Request $request)
    {
        $this->validate($request, [
            'payment_status' => 'required',
            'payment_type' => 'required',
            'location_id' => 'required',
        ]);

        $sale = Sale::find($request->id);
        $sale->payment_status = $request->payment_status;
        $sale->payment_type = $request->payment_type;
        $sale->location_id = $request->location_id;
        $sale->save();
        return response()->json(true);
    }

    public function getUnread()
    {
        $unread = Sale::where('readed', 0)->get()->count();
        return response()->json($unread);
    }

    private function getWeight($weight)
    {
        $val = $weight / 1000;
        return  $val >= 1 ? $val . ' Kg.' : $weight . ' Gr.';
    }

    public function edit($id)
    {
        //
    }

    public function setReaded(Sale $sale)
    {
        $sale->readed = 1;
        $sale->save();
        return response()->json(true);
    }


    public function update(Request $request, $id)
    {
        //
    }

    public function getByUser(Request $request, $user)
    {
       
        $query = Sale::query();
        $query->where('user_id', $user);
        $query->orderBy('created_at', 'DESC');


        $query->skip(intval($request->pag) * 8)->paginate(8);

        $sales = $query->paginate(8);

        return response()->json($sales, 200);
    }

    public function printSale(Sale $sale){
        $pdf = PDF::loadView('reports.sales', ['sales' => [$sale] ]);
        return $pdf->stream(preg_replace('/\s+/', '', $sale->name.'-'.$sale->delivery_date.'.pdf'));
    }

    public function printReport(Request $request){
        
        $filters = [];
        
        $query = Sale::query();
        
        if(isset($request->from)){
            $query->whereDate('created_at','>=',$request->from);
            $filters['from'] = Carbon::createFromFormat('Y-m-d',$request->from)->format('d/m/Y');
        }

        if(isset($request->to)){
            $query->whereDate('created_at','<=',$request->to);
            $filters['to'] = Carbon::createFromFormat('Y-m-d',$request->to)->format('d/m/Y');
        }

        if(isset($request->location_id)){
            $query->where('location_id',$request->location_id);
            $filters['location'] = Location::find($request->location_id)->description;
        }

        $sales = $query->get();
        
        $pdf = PDF::loadView('reports.sales', ['sales' => $sales ,'filters' => $filters ]);
        return $pdf->stream(preg_replace('/\s+/', '','reportes-de-ventas.pdf'));

    }

}
