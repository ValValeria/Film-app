<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use App\Models\Product;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{

   const IS_JSON = 'isjson';
   private User $user;


   public function getProduct(Request $request, $id = null)
   {
      $isJson = $request->query(self::IS_JSON);
      $view = 'admin.pages.product';
      $search = $request->query('search');

      if (Str::contains($request->url(), 'products')) {
         $per_page = $request->query('per_page','7');

         if(!$search){
            $data = Product::paginate(intval($per_page));
         }else{
            $data = Product::query()->where('title', 'like', "%$search%")
                                    ->orWhere('category', 'like', "%$search%")
                                    ->orWhere('short_description', 'like', "%$search%")
                                    ->orWhere('long_description', 'like', "%$search%");
            $data = $data->paginate($per_page);                       
         }

         $view = 'admin.pages.products';
      } else if (is_int(intval($id))) {
         $data = Product::findOrFail($id);
      }

      if ($isJson) {
         return response(json_encode($data, JSON_UNESCAPED_UNICODE), 200);
      } else if (Gate::allows('isadmin')) {
         return view($view)
            ->with('data', $data);
      } else {
         return redirect()->route('login');
      }
   }

   public function deleteProduct(Request $request, Product $product)
   {
      if ($request->user->cannot("delete", $product)) {
         abort(403);
      } else {
         $product->delete();
         return redirect('/admin');
      }
   }

   public function getOrderList()
   {
      if(!Auth::check()){
         abort(403);
      }

      $this->user = Auth::user();
      $order_list = $this->user->orders();
      $data = [];

      foreach ($order_list->get() as $order) {
         $data[] = ["title" => $order->product->title, "quantity" => $order->quantity, "product_id" => $order->product->id, "price" => $order->product->price, "status" => $order->status, "date" => $order->created_at];
      }

      return ["data" => $data];
   }

   public function getIngredients()
   {
      $result = Product::all()->pluck("ingredients");
      $data = [];

      foreach ($result as $value) {
         $arr = json_decode($value,JSON_UNESCAPED_UNICODE)??[];
         $data = array_merge($data,$arr);
      }

      return collect($data);
   }

   public function getData()
   {
      $data = [
         "ingredients" => $this->getIngredients()->unique(),
         "max_price" => Product::all()->max('price'),
         "min_price" => Product::all()->min('price'),
         "max_weight" => Product::all()->max('weight'),
         "min_weight" => Product::all()->max('weight'),
         "categories"=>Product::all()->pluck("category")->unique()
      ];
      return response(json_encode(["data"=>$data],JSON_UNESCAPED_UNICODE))->header('Content-Type','application/json');
   }
}
