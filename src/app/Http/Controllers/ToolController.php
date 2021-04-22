<?php

namespace App\Http\Controllers;

use App\Http\Resources\StoreShops as StoreShopResource;
use App\StoreShop;
use DOMDocument;
use DOMXPath;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
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

        //check account
        $storeShops = [];
        foreach ($storeIds as $storeId) {
            $storeShopOb = StoreShop::find($storeId);
            if (is_null($storeShopOb)) {
                return $this->sendError("id (${$storeId}) not found.");
            }
            $storeShop = new StoreShopResource($storeShopOb);
            if (strtolower($storeShop['type_shop']) === 'shopify') {
                $rsJ = $this->verifyAccountShopify($storeShop);
                $rs = json_decode($rsJ->content(), true);
                if (!$rs['success']) {
                    return $rsJ;
                }
            }

            if (strtolower($storeShop['type_shop']) === 'shopbase') {
                $rsJ = $this->verifyAccountShopbase($storeShop);
                $rs = json_decode($rsJ->content(), true);
                if (!$rs['success']) {
                    return $rsJ;
                }
            }

            array_push($storeShops, $storeShop);
        }


        if ($target == 'collection') {
            if ($source === 'shopify') {
                $arrLink = $this->getArrLinkFromCollectionShopify($link);
                if ($arrLink['success']) {
                    $reviewSite = [];
                    foreach ($arrLink['data'] as $l) {
                        $views = $this->addProduct($l, $storeShops);
                        $reviewSite = array_merge($reviewSite, $views);
                        usleep(1 * 1000000);
                    }
                    return response()->json(['success' => true, 'views' => $reviewSite]);
                }
            } elseif ($source === 'shopbase') {
                $reviewSite = [];
                $arr = parse_url($link);
                $domain = $arr['scheme'] . '://' . $arr['host'];
                $baseGet = $domain . '/api/catalog/products_v2.json?collection_ids=' . $this->getIdShopbase($link) . '&page=';
                $i = 1;
                while ($i >= 1) {
                    $results = $this->getProductByLink($baseGet . $i);
                    $products = json_decode($results['data'], true);
                    $datas = $products['products'];
                    if (!$results['success'] || count($datas) === 0) {
                        break;
                    }
                    $i++;
                    foreach ($datas as $data) {
                        $views = $this->addProductData($data, $storeShops, $source);
                        $reviewSite = array_merge($reviewSite, $views);
                        usleep(1 * 1000000);
                    }
                }

                return response()->json(['success' => true, 'views' => $reviewSite]);
            }

        } else {
            $reviewSite = $this->addProduct($link, $storeShops, $source);
            return response()->json(['success' => true, 'views' => $reviewSite]);
        }

        return response()->json(['success' => true, 'views' => []]);
    }

    private function addProduct($link, $storeShops, $source = 'shopify')
    {
        $link = trim($link);
        if ($link !== '') {
            $sourceData = [];
            $isSuccess = false;
            if ($source === 'shopify') {
                $link = str_replace('.json', '', $link) . '.json';
            }

            if ($source === 'shopbase'){
                $arr = parse_url($link);
                $domain = $arr['scheme'] . '://' . $arr['host'];
                $link = $domain . '/api/catalog/products_v2.json?ids=' . $this->getIdShopbase($link, 'product');
            }

            if ($source === 'shopify' || $source === 'shopbase') {
                $product = $this->getProductByLink($link);
                $isSuccess = $product['success'];
                $productData = json_decode($product['data'], true);
                $sourceData = $source === 'shopify' ? $productData['product'] : $productData['products'][0];
            }

            if ($source === 'teechip'){
                $results = $this->getProductTeechip($link);
                $isSuccess = $results['success'];
                $sourceData = $results['data'];
            }

            if ($source === 'enjoycute'){
                $results = $this->getProductEnjoycute($link);
                $isSuccess = $results['success'];
                $sourceData = $results['data'];
            }


            if ($isSuccess) {
                $reviewSite = [];
                foreach ($storeShops as $storeShop) {
                    $results = ['success' => false];
                    if (strtolower($storeShop['type_shop']) === 'shopify') {
                        $results = $this->addProductShopify($sourceData, $source, $storeShop);
                    }

                    if (strtolower($storeShop['type_shop']) === 'shopbase') {
                        $results = $this->addProductShopbase($sourceData, $source, $storeShop);
                    }

                    if ($results['success']) {
                        array_push($reviewSite, [
                            "link" => $storeShop['store_front'] . $sourceData['handle'],
                            "title" => $sourceData['title']
                        ]);
                    } else {
                        if (array_key_exists('error', $results)) {
                            array_push($reviewSite, [
                                "link" => '',
                                "title" => $results['error']
                            ]);
                        }
                    }
                }

                return $reviewSite;

            }
        }
    }

    private function addProductData($data, $storeShops, $source = 'shopify')
    {
        $totalKey = count((array)$data);
        if ($totalKey > 0) {
            $reviewSite = [];

            foreach ($storeShops as $storeShop) {
                $results = ['success' => false];
                if (strtolower($storeShop['type_shop']) === 'shopify') {
                    $results = $this->addProductShopify($data, $source, $storeShop);
                }

                if (strtolower($storeShop['type_shop']) === 'shopbase') {
                    $results = $this->addProductShopbase($data, $source, $storeShop);
                }

                if ($results['success']) {
                    array_push($reviewSite, [
                        "link" => $storeShop['store_front'] . $data['handle'],
                        "title" => $data['title']
                    ]);
                } else {
                    if (array_key_exists('error', $results)) {
                        array_push($reviewSite, [
                            "link" => '',
                            "title" => $results['error']
                        ]);
                    }
                }
            }

            return $reviewSite;
        }
    }

    private function getArrLinkFromCollectionShopify($link)
    {
        $parse = parse_url($link);
        $domain = $parse['scheme'] . '://' . $parse['host'];
        $link = trim($link);
        $baseGet = 'https://ncu8zq1h33.execute-api.us-west-2.amazonaws.com/default/shopify_collections_extractor?url=' . $link . '?page=';
        $results = $this->getProductByLink($baseGet . '1');
        $data = json_decode($results['data'], true);
        if (!$results['success'] || count($data) === 0) {
            return [
                'success' => false,
                'data' => []
            ];
        }
        $arrLink = [];
        array_push($arrLink, $data);

        $i = 2;
        while ($i >= 2) {
            $results = $this->getProductByLink($baseGet . $i);
            $data = json_decode($results['data'], true);
            if (!$results['success'] || count($data) === 0) {
                break;
            }
            $i++;
            array_push($arrLink, $data);
        }

        $arrLinkValid = [];
        foreach ($arrLink as $v) {
            foreach ($v as $link) {
                if (!in_array($link, $arrLinkValid)) {
                    array_push($arrLinkValid, $domain . $link . '.json');
                }
            }
        }
        return [
            'success' => true,
            'data' => $arrLinkValid
        ];
    }

    private function getProductByLink($link)
    {
        $client = new Client();
        $request = $client->get($link);
        if ($request->getStatusCode() === 200) {
            return [
                'success' => true,
                'data' => $request->getBody()->getContents()
            ];
        }

        return [
            'success' => false,
            'data' => []
        ];
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
            return $this->sendError("id (${storeShop['id']}) invalid.");
        }

        return $this->sendError("id (${storeShop['id']}) invalid.");
    }

    private function addImageShopify($data, $storeShop)
    {
        $client = new Client();
        $productId = $data['product_id'];
        $endpoint = "https://${storeShop['api_key']}:${storeShop['secret_key']}@${storeShop['store_name']}.myshopify.com/admin/api/2021-01/products/${productId}/images.json";
        $request = $client->post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['image' => $data])
        ]);
        if ($request->getStatusCode() === 200 || $request->getStatusCode() === 201) {
            return [
                'success' => true,
                'data' => $request->getBody()->getContents()
            ];
        }

        return [
            'success' => false,
            'data' => []
        ];
    }

    private function addProductShopify($product, $source, $storeShop)
    {
        $data = [];
        $images = [];
        if (array_key_exists('images', $product)) {
            $images = $product['images'];
            unset($product['images']);
        }

        if (array_key_exists('image', $product)) {
            unset($product['image']);
        }

        $keys = [];
        $idValueOptions = [];
        if ($source === 'shopbase') {
            $options = [];
            if (array_key_exists('option_sets', $product)) {
                $options = $product['option_sets'];
                unset($product['option_sets']);
            }
            $product['published_at'] = date("Y-m-d H:i:s", $product['published_at']);
            $data['options'] = [];
            foreach ($options as $kop=>$op) {
                $values = [];
                foreach ($op['options'] as $sop) {
                    $idValueOptions[$sop['id']] = $sop;
                    array_push($values, $sop['value']);
                    if (!in_array($sop['value'], $keys)) {
                        array_push($keys, $sop['id']);
                    }
                }
                array_push($data['options'], [
                    "name" => $op['value'],
                    "position" => $kop + 1,
                    "values" => $values
                ]);
            }
        }

        $variantIdAndSku = [];
        foreach ($product as $k => $v) {
            if ($k === 'variants') {
                foreach ($product[$k] as $sk => $sv) {
                    if (array_key_exists('sku', $sv)) {
                        if (strlen(trim(strval($sv['sku']))) > 0) {
                            $variantIdAndSku[$sv['id']] = ['sku' => $sv['sku'], 'id' => $sv['id']];
                        }
                    } else {
                        $variantIdAndSku[$sv['id']] = ['title' => $sv['title'], 'id' => $sv['id']];
                    }
                    $data[$k][$sk] = $sv;
                    unset($data[$k][$sk]['image_id']);
                    unset($data[$k][$sk]['fulfillment_service']);

                    if (count($keys) > 0) {
                        $countOption = count($data['options']);
                        for ($i = 1; $i <= $countOption; $i++) {
                            $option = 'option'.$i;
                            if (in_array($sv[$option], $keys) && !empty($data[$k][$sk])) {
                                $data[$k][$sk][$option] = $idValueOptions[$sv[$option]]['value'];
                            } else {
                                unset($data[$k][$sk]);
                            }
                        }
                    }
                }
            } elseif ($k === 'body_html') {
                $data['body_html'] = preg_replace("/<a href=.*?>(.*?)<\/a>/", "", $v);
                $data['body_html'] = str_replace("today", "", $data['body_html']);
                $data['body_html'] = str_replace("Today", "", $data['body_html']);
                $data['body_html'] = str_replace("TODAY", "", $data['body_html']);
            } else {
                $data[$k] = $v;
            }

        }

        if (count($data['variants']) > 100) {
            $data['variants'] = array_slice( $data['variants'], 0, 100);
        }

        $data['variants'] = array_values($data['variants']);
        $client = new Client();
        $endpoint = "https://${storeShop['api_key']}:${storeShop['secret_key']}@${storeShop['store_name']}.myshopify.com/admin/api/2021-01/products.json";
        $request = $client->post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['product' => $data])
        ]);

        if ($request->getStatusCode() === 200 || $request->getStatusCode() === 201) {
            $product = $request->getBody()->getContents();
            $product_d = json_decode($product, true);

            foreach ($product_d['product']['variants'] as $variant) {
                foreach ($variantIdAndSku as $k => $val) {
                    if (array_key_exists('sku', $val)) {
                        if ($val['sku'] === $variant['sku']) {
                            $variantIdAndSku[$k]['new_id'] = $variant['id'];
                        }
                    } else {
                        if ($val['title'] === $variant['title']) {
                            $variantIdAndSku[$k]['new_id'] = $variant['id'];
                        }
                    }
                }
            }

            foreach ($images as $i) {
                $i['product_id'] = $product_d['product']['id'];
                $dataI = $this->mapVariantIdAfterInsert($i, $variantIdAndSku);
                $this->addImageShopify($dataI, $storeShop);
                usleep(1 * 1000000);
            }
            return [
                'success' => true,
                'data' => $product
            ];
        }

        return [
            'success' => false,
            'data' => []
        ];
    }

    private function getIdShopbase($link, $source = 'collection')
    {
        $html = file_get_contents($link);
        preg_match_all('/<script>(.*?)<\/script>/s', $html, $matches);
        $id = null;
        if (count($matches) > 0) {
            foreach ($matches as $k => $v) {
                foreach ($v as $sk => $sv) {
                    $strValue = str_replace(' ', '', strval($sv));
                    if (strpos($strValue, 'window.__INITIAL_STATE__=') !== false) {
                        $strData = str_replace(
                            'window.__INITIAL_STATE__=',
                            '',
                            $sv);
                        $strData = str_replace(
                            ';(function(){var s;(s=document.currentScript||document.scripts[document.scripts.length-1]).parentNode.removeChild(s);}());',
                            '',
                            $strData);
                        $data = json_decode($strData, true);
                        if (json_last_error() === JSON_ERROR_NONE || json_last_error() === 0) {
                            if ($source === 'collection') {
                                if (array_key_exists('collection', $data)) {
                                    if (array_key_exists('collection', $data['collection'])) {
                                        if (array_key_exists('id', $data['collection']['collection'])) {
                                            $id = $data['collection']['collection']['id'];
                                        }
                                    }
                                }
                            } else {
                                if (array_key_exists('customProduct', $data)) {
                                    if (array_key_exists('product', $data['customProduct'])) {
                                        if (array_key_exists('id', $data['customProduct']['product'])) {
                                            $id = $data['customProduct']['product']['id'];
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        return $id;
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
            return $this->sendError("id (${storeShop['id']}) invalid.");
        }

        return $this->sendError("id (${storeShop['id']}) invalid.");
    }

    private function addImageShopbase($data, $storeShop)
    {
        $client = new Client();
        $productId = $data['product_id'];
        $endpoint = "https://${storeShop['api_key']}:${storeShop['secret_key']}@${storeShop['store_name']}.onshopbase.com/admin/products/${productId}/images.json";
        $request = $client->post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['image' => $data])
        ]);

        if ($request->getStatusCode() === 200 || $request->getStatusCode() === 201) {
            return [
                'success' => true,
                'data' => $request->getBody()->getContents()
            ];
        }

        return [
            'success' => false,
            'data' => []
        ];
    }

    private function addProductShopbase($product, $source, $storeShop)
    {
        $data = [];
        $images = [];
        if (array_key_exists('images', $product)) {
            $images = $product['images'];
            unset($product['images']);
        }

        if (array_key_exists('image', $product)) {
            unset($product['image']);
        }

        $keys = [];
        $idValueOptions = [];
        if ($source === 'shopbase') {
            $options = [];
            if (array_key_exists('option_sets', $product)) {
                $options = $product['option_sets'];
                unset($product['option_sets']);
            }
            $data['options'] = [];
            foreach ($options as $kop=>$op) {
                $values = [];
                foreach ($op['options'] as $sop) {
                    $idValueOptions[$sop['id']] = $sop;
                    array_push($values, $sop['value']);
                    if (!in_array($sop['value'], $keys)) {
                        array_push($keys, $sop['id']);
                    }

                }
                array_push($data['options'], [
                    "name" => $op['value'],
                    "position" => $kop + 1,
                    "values" => $values
                ]);
            }
        }
        $variantIdAndSku = [];
        foreach ($product as $k => $v) {
            if ($k === 'images') {
                foreach ($product[$k] as $sk => $sv) {
                    $data[$k][$sk]['position'] = $sv['position'];
                    $data[$k][$sk]['src'] = $sv['src'];
                }
            } elseif ($k === 'variants') {
                foreach ($product[$k] as $sk => $sv) {
                    if (array_key_exists('sku', $sv)) {
                        if (strlen(trim(strval($sv['sku']))) > 0) {
                            $variantIdAndSku[$sv['id']] = ['sku' => $sv['sku'], 'id' => $sv['id']];
                        }
                    } else {
                        $variantIdAndSku[$sv['id']] = ['title' => $sv['title'], 'id' => $sv['id']];
                    }

                    $data[$k][$sk] = $sv;
                    if(count($keys) > 0) {
                        $countOption = count($data['options']);
                        for ($i = 1; $i <= $countOption; $i++) {
                            $option = 'option'.$i;
                            if (in_array($sv[$option], $keys) && !empty($data[$k][$sk])) {
                                $data[$k][$sk][$option] = $idValueOptions[$sv[$option]]['value'];
                            } else {
                                unset($data[$k][$sk]);
                            }
                        }
                    }
                }
            } elseif ($k === 'body_html') {
                $data['body_html'] = preg_replace("/<a href=.*?>(.*?)<\/a>/", "", $v);
                $data['body_html'] = str_replace("today", "", $data['body_html']);
                $data['body_html'] = str_replace("Today", "", $data['body_html']);
                $data['body_html'] = str_replace("TODAY", "", $data['body_html']);
            } else {
                $data[$k] = $v;
            }
        }
        $data['variants'] = array_values($data['variants']);
        if (count($data['variants']) > 250) {
            $data['variants'] = array_slice( $data['variants'], 0, 250);
        }
        unset($data['tags']);


        $client = new Client();
        $endpoint = "https://${storeShop['api_key']}:${storeShop['secret_key']}@${storeShop['store_name']}.onshopbase.com/admin/products.json";
        $request = $client->post($endpoint, [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['product' => $data])
        ]);

        if ($request->getStatusCode() === 200 || $request->getStatusCode() === 201) {
            $product = $request->getBody()->getContents();
            $product_d = json_decode($product, true);
            foreach ($product_d['product']['variants'] as $variant) {
                foreach ($variantIdAndSku as $k => $val) {
                    if (array_key_exists('sku', $val)) {
                        if ($val['sku'] === $variant['sku']) {
                            $variantIdAndSku[$k]['new_id'] = $variant['id'];
                        }
                    } else {
                        if ($val['title'] === $variant['title']) {
                            $variantIdAndSku[$k]['new_id'] = $variant['id'];
                        }
                    }
                }
            }
            foreach ($images as $i) {
                $i['product_id'] = $product_d['product']['id'];
                $dataI = $this->mapVariantIdAfterInsert($i, $variantIdAndSku);
                $this->addImageShopbase($dataI, $storeShop);
                usleep(1 * 1000000);
            }
            return [
                'success' => true,
                'data' => $product
            ];
        }

        return [
            'success' => false,
            'data' => []
        ];
    }

    private function mapVariantIdAfterInsert($dataImage, $variantIdAndSku)
    {
        $VariantIds = [];
        if (array_key_exists('variant_ids', $dataImage)) {
            foreach ($variantIdAndSku as $k => $val) {
                if (in_array($k, $dataImage['variant_ids']) && array_key_exists('new_id', $val)) {
                    array_push($VariantIds, $val['new_id']);
                }
            }
            $dataImage['variant_ids'] = $VariantIds;
        }

        return $dataImage;
    }

    private function getProductTeechip($link)
    {
        $html = file_get_contents($link);
        $tagScript = strip_tags($html, '<script>');
        $string = str_replace("<script", "|||||<script", $tagScript);
        $string = str_replace("</script>", "</script>|||||", $string);
        $arr = explode("|||||", $string);
        $strData = null;
        if (count($arr) > 0) {
            foreach ($arr as $k => $v) {
                if (strpos($v, 'window.__INITIAL_STATE__') !== false) {
                    $strData = str_replace('<script nonce="**CSP_NONCE**">', '', $v);
                    $strData = str_replace('</script>', '', $strData);
                    $strData = str_replace('<script>', '', $strData);
                    $strData = str_replace(
                        'window.__INITIAL_STATE__=',
                        '',
                        $strData);
                    $strData = str_replace(
                        'window.__INITIAL_STATE__ =',
                        '',
                        $strData);
                    $strData = str_replace(
                        ';(function(){var s;(s=document.currentScript||document.scripts[document.scripts.length-1]).parentNode.removeChild(s);}());',
                        '',
                        $strData);
                    $strData = trim($strData);
                    $lastCharacter = substr($strData, -1);
                    if ($lastCharacter === ';') {
                        $strData = substr($strData, 0, -1);
                    }
                    $data = json_decode($strData, true);
                    if (json_last_error() === JSON_ERROR_NONE || json_last_error() === 0) {
                        @$doc = new DOMDocument();
                        @$doc->loadHTML($html);
                        $xpath = new DomXPath($doc);
                        $nodeList = $xpath->query("//div[@class='bc-grey-200 bwt-1 w-full']");
                        $innerHTML= '<div>';
                        for ($i = 0; $i < $nodeList->length; $i++) {
                            $node = $nodeList->item($i);
                            $children = $node->childNodes;
                            foreach ($children as $child) {
                                $innerHTML .= $child->ownerDocument->saveXML($child);
                            }
                        }
                        $data['body_html'] = $innerHTML.'</div>';

                        $dataFormat = $this->convertTeechipToShopify($data);
                        return [
                            'success' => true,
                            'data' => $dataFormat
                        ];
                    }
                }
            }
        }

        return [
            'success' => false,
            'data' => []
        ];
    }

    private function convertTeechipToShopify($data)
    {
        $title = '';
        $sizeAll = ['S', 'M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL'];
        $sizeAllWithoutS = ['M', 'L', 'XL', '2XL', '3XL', '4XL', '5XL', '6XL'];
        $colors = [];
        $nameOptions = [];
        $sizesMulti = [];
        $tagsMulti = [];
        $images = [];
//        $imagesCheck = [];
//        $imagesKeyValue = [];
        $variants = [];
        $productsFetch = $data['vias']['RetailProduct']['docs']['code'];
        $codeCurrent = $data['routing']['locationBeforeTransitions']['query']['retailProductCode'];
        $sameCodeCurrent = explode("-", $codeCurrent);
        $groupCodeCurrent = $sameCodeCurrent[3];

        $positionImage = 1;
        $variantsCheck = [];
        if (count($productsFetch) > 0) {
            $title = $productsFetch[$codeCurrent]['doc']['names']['design'];
            foreach ($productsFetch as $k => $p) {
                $sameCode = explode("-", $k);
                $groupCode = $sameCode[3];
                if ($groupCodeCurrent === $groupCode) {
                    $product = $p['doc'];
                    if (!in_array($product['color'], $colors)) {
                        array_push($colors, $product['color']);
                    }

                    $title = $product['names']['design'] . ' ' . $product['names']['product'];
                    $price = $product['price'];
                    if ($price > 0) {
                        $price = $price / 100;
                    }
                    $tags = $product['tags']['product'];

                    foreach ($tags as $tag_key => $tag) {
                        if (!in_array($tag, $tagsMulti)) {
                            array_push($tagsMulti, $tag);
                        }
                    }

                    $productId = $this->bigNumber();

                    $imagesFetch = $product['images'];
                    $sizesByVariant = [];
                    foreach ($imagesFetch as $k_img => $img) {
                        $sizes = $img['sizes'];
                        $nameOp = $img['name'];
                        if (!in_array($nameOp, $nameOptions)) {
                            array_push($nameOptions, $nameOp);
                        }

                        foreach ($sizes as $k_size => $size) {
                            $size = $size['size'];
                            $sizeInsert = [];
                            if (strtolower($size) === 'all') {
                                $sizeInsert = $sizeAll;
                            } else {
                                $sizeInsert = [$size];
                            }

                            foreach ($sizeInsert as $s) {
                                if (!in_array($s, $sizesMulti)) {
                                    array_push($sizesMulti, $s);
                                }

                                if (!in_array($s, $sizesByVariant)) {
                                    array_push($sizesByVariant, $s);
                                }
                            }
                        }


                        foreach ($sizesByVariant as $sizeOp) {
                            $variantId = $this->bigNumber();
                            $codeOp = trim(str_replace(' ', '_', $nameOp.$product['color'].$sizeOp));
                            $sku =  trim(str_replace(' ', '', $nameOp)).'-'.trim(str_replace(' ', '', $product['color'])).'-'.trim(str_replace(' ', '', $sizeOp));
                            if (!in_array($codeOp, $variantsCheck)) {
                                array_push($variantsCheck, $codeOp);
                                array_push($variants, [
                                    'id' => $variantId,
                                    'title' => $title,
                                    'product_id' => $productId,
                                    'price' => $price,
                                    'sku' => $sku,
                                    "option1" => $nameOp,
                                    "option2" => $product['color'],
                                    "option3" => $sizeOp,
                                ]);

                                if (in_array(strtoupper($sizeOp), $sizeAllWithoutS)) {
                                    array_push($images[count($images) - 1]['variant_ids'], $variantId);
                                } else {
                                    $awsUrl = '';
                                    $url = $img['prefix'].'/regular.jpg';
                                    $contents = file_get_contents($url);
                                    $name = substr($url, strrpos($url, '/') + 1);
                                    $name = explode('?', $name);
                                    $fileName = $name[0];
                                    $filePath = 'teechip/products/'.$k.'/'.$fileName;
                                    $awsSaved = Storage::disk('s3')->put($filePath, $contents, 'public');
                                    if ($awsSaved) {
                                        $awsUrl = Storage::disk('s3')->url($filePath);
                                    }

                                    array_push($images, [
                                        'position' => $positionImage,
                                        'src' => $awsUrl,
                                        'variant_ids' => [$variantId],
                                    ]);

                                    $positionImage++;
                                }


                            }
                        }



                    }
                }
            }
        }

        $options = [
            ['name' => 'Surface', 'position' => 1, 'values' => $nameOptions],
            ['name' => 'Color', 'position' => 2, 'values' => $colors],
            ['name' => 'Size', 'position' => 3, 'values' => $sizesMulti],
        ];

        return [
            'title' => $title,
            'body_html' => $data['body_html'],
            'handle' => $codeCurrent,
            'vendor' => '',
            'product_type' => '',
            'published_at' => date('Y-m-d H:i:s'),
            'published_scope' => 'web',
            'status' => 'active',
            "tags" => $tagsMulti,
            "options" => $options,
            "variants" => $variants,
            "images" => $images,
        ];
    }

    private function getProductEnjoycute($link)
    {
        $html = file_get_contents($link);
        @$doc = new DOMDocument();
        @$doc->loadHTML($html);
        $nodeList = $doc->getElementsByTagName('v-commoditydetail');
        $strData = $nodeList->item(0)->getAttribute(':product');
        $data = json_decode($strData, true);
        if (json_last_error() === JSON_ERROR_NONE || json_last_error() === 0) {
            $dataFormat = $this->enjoycuteToShopify($data);
            return [
                'success' => true,
                'data' => $dataFormat
            ];
        }

        return [
            'success' => false,
            'data' => []
        ];
    }

    private function enjoycuteToShopify($data) {
        $options = [];
        foreach ($data['variant_attrs'] as $kOp=>$vOp) {
            $option = [
                'name' => $vOp['name'],
                'position' => $kOp + 1,
                'values' => $vOp['value'],
            ];
            array_push($options, $option);
        }

        $images = [];
        $variants = [];
        foreach ($data['variants'] as $kV=>$vV) {
            $variant = [
                'id' => $vV['ID'],
                'title' => $vV['title'],
                'product_id' => $data['ID'],
                'price' => $vV['sale_price'],
                'compare_at_price'=> $vV['regular_price'],
                'sku' => $vV['sku']
            ];
            for ($i = 0; $i < count($vV['attrs']); $i++) {
                if ($i === 3) break;
                $variant['option'.($i + 1)] = $vV['attrs'][$i]['value'];
            }
            array_push($variants, $variant);

            $image = [
                'position' => $kV + 1,
                'src' => $vV['image'],
                'variant_ids' => [$variant['id']],
            ];
            array_push($images, $image);
        }

        return [
            'title' => $data['title'],
            'body_html' => $data['post_content'],
            'handle' => $data['slug'],
            'vendor' => '',
            'product_type' => '',
            'published_at' => date('Y-m-d H:i:s'),
            'published_scope' => 'web',
            'status' => 'active',
            "tags" => [],
            "options" => $options,
            "variants" => $variants,
            "images" => $images,
        ];
    }

    private function bigNumber() {
        $output = rand(1,9);

        for($i=0; $i<14; $i++) {
            $output .= rand(0,9);
        }

        return intval($output);
    }
}
