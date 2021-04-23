<?php

namespace App\Http\Controllers;

use App\Http\Resources\StoreShops as StoreShopResource;
use App\StoreShop;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;


/**
 * @group  Import management
 *
 * APIs for managing Imports
 */
class ToolController extends BaseController
{

    /**
     *  Import product
     *
     * @bodyParam  link string required url product. Example: https://personalizethem.com/collections/all/products/1102
     * @bodyParam  source string required shopify, shopbase, teechip, shoplaza. Example: shopify
     * @bodyParam  target string required product, collection. Example: product
     * @bodyParam  store_ids array required array store id. Example: [1,2,3]
     */
    public function import(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'link' => 'required',
            'target' => 'in:product,collection',
            'source' => 'in:shopify,shopbase,teechip,shoplaza',
            'store_ids' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->sendError('Validation Error.', $validator->errors());
        }

        $link = $request->get('link', '');
        $target = $request->get('target', '');
        $source = $request->get('source', '');
        $storeIds = $request->get('store_ids', []);
        if (strlen(trim($link)) === 0 || strlen(trim($target)) === 0 || strlen(trim($source)) === 0 || count($storeIds) === 0) {
            return $this->sendError('Data input is invalid.');
        }

        $storeShops = [];
        foreach ($storeIds as $storeId) {
            $storeShopOb = StoreShop::find($storeId);
            if (is_null($storeShopOb)) {
                return $this->sendError("id (${$storeId}) not found.");
            }
            $storeShop = new StoreShopResource($storeShopOb);
            array_push($storeShops, $storeShop);
        }

        return $this->callImportCore($link, $target, $source, $storeShops);
    }

    private function callImportCore($link, $target, $source, $storeShops)
    {
        $payload = [
            'link' => $link,
            'source' => $source,
            'target' => $target,
            'store_shops' => $storeShops,
        ];

        $client = new Client();
        $request = $client->post("http://54.151.242.94/api/import", [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode($payload)
        ]);
        if ($request->getStatusCode() === 200 || $request->getStatusCode() === 201) {
            return response()->json(json_decode($request->getBody()->getContents(), true));
        }

        return response()->json(['success' => false, 'views' => []]);
    }

}
