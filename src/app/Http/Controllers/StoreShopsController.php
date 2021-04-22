<?php

namespace App\Http\Controllers;

use App\Http\Resources\StoreShops as StoreShopResource;
use App\StoreShop;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


/**
 * @group  Store management
 *
 * APIs for managing Stores
 */
class StoreShopsController extends BaseController
{
    /**
     * Get list stores
     *
     * @queryParam  customer_id
     */
    public function index(Request $request)
    {
        $customerId = $request->query('customer_id', null);
        $query = StoreShop::query();
        if ($customerId !== null) {
            $query->where('customer_id', '=', $customerId);
        }
        $storeShop = $query->get();

        return $this->sendResponse(StoreShopResource::collection($storeShop), 'StoreShops Retrieved Successfully.');
    }

    /**
     * Create a store
     *
     * @bodyParam  type_shop string required enum: SHOPBASE, SHOPIFY. Example: SHOPBASE
     * @bodyParam  store_name string required name store. Example: shop00000001
     * @bodyParam  store_front string required domain front-end. Example: https://www.leuleushop.com/products/
     * @bodyParam  api_key string required api key store. Example: 754cfb1d640725d4e33e7d1e0cc59982
     * @bodyParam  secret_key string required secret key store. Example: b139dc630ddb613b30946ba29e870f48611f6858cd7c4a154fce17b264aa1eaa
     * @bodyParam  customer_id int required customer id. Example: 68
     */
    public function store(Request $request)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'type_shop' => 'in:SHOPIFY,SHOPBASE',
            'store_name' => 'required',
            'store_front' => 'required',
            'api_key' => 'required',
            'secret_key' => 'required',
            'customer_id' => 'required|numeric|min:0|not_in:0',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors(), 400);
        }

        $storeShop = [
            'type_shop' => $request->get('type_shop', ''),
            'store_name' => $request->get('store_name', ''),
            'store_front' => $request->get('store_front', ''),
            'api_key' => $request->get('api_key', ''),
            'secret_key' => $request->get('secret_key', ''),
        ];

        if (strtoupper($storeShop['type_shop']) === 'SHOPIFY') {
            $rsJ = $this->verifyAccountShopify($storeShop);
            $rs = json_decode($rsJ->content(), true);
            if (!$rs['success']) {
                return $rsJ;
            }
        }

        if (strtoupper($storeShop['type_shop']) === 'SHOPBASE') {
            $rsJ = $this->verifyAccountShopbase($storeShop);
            $rs = json_decode($rsJ->content(), true);
            if (!$rs['success']) {
                return $rsJ;
            }
        }

        $storeShop = StoreShop::create($input);

        return $this->sendResponse(new StoreShopResource($storeShop), 'StoreShop Created Successfully.');
    }

    /**
     * Get a store
     *
     * @urlParam  store required is ID of the store.
     */
    public function show($id)
    {
        $storeShop = StoreShop::find($id);

        if (is_null($storeShop)) {
            return $this->sendError('StoreShop not found.');
        }

        return $this->sendResponse(new StoreShopResource($storeShop), 'StoreShop Retrieved Successfully.');
    }

    /**
     * Update a store
     *
     * @urlParam  store required is ID of the store.
     * @bodyParam  type_shop string required enum: SHOPBASE, SHOPIFY. Example: SHOPBASE
     * @bodyParam  store_name string required name store. Example: shop00000001
     * @bodyParam  store_front string required domain front-end. Example: https://www.leuleushop.com/products/
     * @bodyParam  api_key string required api key store. Example: 754cfb1d640725d4e33e7d1e0cc59982
     * @bodyParam  secret_key string required secret key store. Example: b139dc630ddb613b30946ba29e870f48611f6858cd7c4a154fce17b264aa1eaa
     * @bodyParam  customer_id int required customer id. Example: 68
     */
    public function update(Request $request, $id)
    {
        $input = $request->all();

        $validator = Validator::make($input, [
            'type_shop' => 'in:SHOPIFY,SHOPBASE',
            'store_name' => 'required',
            'store_front' => 'required',
            'api_key' => 'required',
            'secret_key' => 'required',
            'customer_id' => 'required|numeric|min:0|not_in:0',
        ]);

        if($validator->fails()){
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $storeShop = [
            'type_shop' => $request->get('type_shop', ''),
            'store_name' => $request->get('store_name', ''),
            'store_front' => $request->get('store_front', ''),
            'api_key' => $request->get('api_key', ''),
            'secret_key' => $request->get('secret_key', ''),
        ];

        if (strtoupper($storeShop['type_shop']) === 'SHOPIFY') {
            $rs = $this->verifyAccountShopify($storeShop);
            if (!$rs['success']) {
                return $this->sendError($rs['message']);
            }
        }

        if (strtoupper($storeShop['type_shop']) === 'SHOPBASE') {
            $rs = $this->verifyAccountShopbase($storeShop);
            if (!$rs['success']) {
                return $this->sendError($rs['message']);
            }
        }

        $storeShop = StoreShop::find($id);
        $storeShop->type_shop = $input['type_shop'];
        $storeShop->store_name = $input['store_name'];
        $storeShop->store_front = $input['store_front'];
        $storeShop->api_key = $input['api_key'];
        $storeShop->secret_key = $input['secret_key'];
        $storeShop->customer_id = $input['customer_id'];
        $storeShop->save();

        return $this->sendResponse(new StoreShopResource($storeShop), 'StoreShop Updated Successfully.');
    }

    /**
     * Delete a store
     *
     * @urlParam  store required is ID of the store.
     */
    public function destroy($id)
    {
        $storeShop = StoreShop::find($id);
        $storeShop->delete();

        return $this->sendResponse([], 'StoreShop Deleted Successfully.');
    }

    private function verifyAccountShopify($storeShop)
    {
        $client = new Client();
        $endpoint = "https://${storeShop['api_key']}:${storeShop['secret_key']}@${storeShop['store_name']}.myshopify.com/admin/api/2021-01/products.json";

        try {
            $request = $client->get($endpoint, [
                'headers' => ['Content-Type' => 'application/json']
            ]);
            if ($request->getStatusCode() === 200 || $request->getStatusCode() === 201) {
                return $this->sendResponse([], 'ok');
            }
        }catch (ClientException $ex) {
            return $this->sendError("store name (${storeShop['store_name']}) invalid.");
        }

        return $this->sendError("store name (${storeShop['store_name']}) invalid.");
    }

    private function verifyAccountShopbase($storeShop)
    {
        $client = new Client();
        $endpoint = "https://${storeShop['api_key']}:${storeShop['secret_key']}@${storeShop['store_name']}.onshopbase.com/admin/products.json";

        try {
            $request = $client->get($endpoint, [
                'headers' => ['Content-Type' => 'application/json']
            ]);
            if ($request->getStatusCode() === 200 || $request->getStatusCode() === 201) {
                return $this->sendResponse([], 'ok');
            }
        }catch (ClientException $ex) {
            return $this->sendError("store name (${storeShop['store_name']}) invalid.");
        }

        return $this->sendError("store name (${storeShop['store_name']}) invalid.");
    }
}
