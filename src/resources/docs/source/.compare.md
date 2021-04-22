---
title: API Reference

language_tabs:
- bash
- javascript

includes:

search: true

toc_footers:
- <a href='http://github.com/mpociot/documentarian'>Documentation Powered by Documentarian</a>
---
<!-- START_INFO -->
# Info

Welcome to the generated API reference.
[Get Postman Collection](http://localhost/docs/collection.json)

<!-- END_INFO -->

#Import management


APIs for managing Imports
<!-- START_22c03829eae13dc0c2bd6a66e55983f4 -->
## Import product

> Example request:

```bash
curl -X POST \
    "http://localhost/api/import" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"link":"https:\/\/personalizethem.com\/collections\/all\/products\/1102","source":"shopify","target":"product","store_ids":"[1,2,3]"}'

```

```javascript
const url = new URL(
    "http://localhost/api/import"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "link": "https:\/\/personalizethem.com\/collections\/all\/products\/1102",
    "source": "shopify",
    "target": "product",
    "store_ids": "[1,2,3]"
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/import`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `link` | string |  required  | url product.
        `source` | string |  required  | shopify, shopbase, teechip, shoplaza.
        `target` | string |  required  | product, collection.
        `store_ids` | array |  required  | array store id.
    
<!-- END_22c03829eae13dc0c2bd6a66e55983f4 -->

#Store management


APIs for managing Stores
<!-- START_03ef733a4a0de089e74fe4c8dc863453 -->
## Get list stores

> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/stores?customer_id=nihil" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/stores"
);

let params = {
    "customer_id": "nihil",
};
Object.keys(params)
    .forEach(key => url.searchParams.append(key, params[key]));

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (200):

```json
{
    "success": true,
    "data": [],
    "message": "StoreShops Retrieved Successfully."
}
```

### HTTP Request
`GET api/stores`

#### Query Parameters

Parameter | Status | Description
--------- | ------- | ------- | -----------
    `customer_id` |  optional  | 

<!-- END_03ef733a4a0de089e74fe4c8dc863453 -->

<!-- START_318c837814faa65bd129e59fb8c48161 -->
## Create a store

> Example request:

```bash
curl -X POST \
    "http://localhost/api/stores" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"type_shop":"SHOPBASE","store_name":"shop00000001","store_front":"https:\/\/www.leuleushop.com\/products\/","api_key":"754cfb1d640725d4e33e7d1e0cc59982","secret_key":"b139dc630ddb613b30946ba29e870f48611f6858cd7c4a154fce17b264aa1eaa","customer_id":68}'

```

```javascript
const url = new URL(
    "http://localhost/api/stores"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "type_shop": "SHOPBASE",
    "store_name": "shop00000001",
    "store_front": "https:\/\/www.leuleushop.com\/products\/",
    "api_key": "754cfb1d640725d4e33e7d1e0cc59982",
    "secret_key": "b139dc630ddb613b30946ba29e870f48611f6858cd7c4a154fce17b264aa1eaa",
    "customer_id": 68
}

fetch(url, {
    method: "POST",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`POST api/stores`

#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `type_shop` | string |  required  | enum: SHOPBASE, SHOPIFY.
        `store_name` | string |  required  | name store.
        `store_front` | string |  required  | domain front-end.
        `api_key` | string |  required  | api key store.
        `secret_key` | string |  required  | secret key store.
        `customer_id` | integer |  required  | customer id.
    
<!-- END_318c837814faa65bd129e59fb8c48161 -->

<!-- START_beaa468a5b60ecf8a4cd7aa0c3de498a -->
## Get a store

> Example request:

```bash
curl -X GET \
    -G "http://localhost/api/stores/dolorem" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/stores/dolorem"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "GET",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```


> Example response (400):

```json
{
    "success": false,
    "message": "StoreShop not found."
}
```

### HTTP Request
`GET api/stores/{store}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `store` |  required  | is ID of the store.

<!-- END_beaa468a5b60ecf8a4cd7aa0c3de498a -->

<!-- START_3448340d4059d38a6ecb6459d133895d -->
## Update a store

> Example request:

```bash
curl -X PUT \
    "http://localhost/api/stores/doloribus" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json" \
    -d '{"type_shop":"SHOPBASE","store_name":"shop00000001","store_front":"https:\/\/www.leuleushop.com\/products\/","api_key":"754cfb1d640725d4e33e7d1e0cc59982","secret_key":"b139dc630ddb613b30946ba29e870f48611f6858cd7c4a154fce17b264aa1eaa","customer_id":68}'

```

```javascript
const url = new URL(
    "http://localhost/api/stores/doloribus"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

let body = {
    "type_shop": "SHOPBASE",
    "store_name": "shop00000001",
    "store_front": "https:\/\/www.leuleushop.com\/products\/",
    "api_key": "754cfb1d640725d4e33e7d1e0cc59982",
    "secret_key": "b139dc630ddb613b30946ba29e870f48611f6858cd7c4a154fce17b264aa1eaa",
    "customer_id": 68
}

fetch(url, {
    method: "PUT",
    headers: headers,
    body: body
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`PUT api/stores/{store}`

`PATCH api/stores/{store}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `store` |  required  | is ID of the store.
#### Body Parameters
Parameter | Type | Status | Description
--------- | ------- | ------- | ------- | -----------
    `type_shop` | string |  required  | enum: SHOPBASE, SHOPIFY.
        `store_name` | string |  required  | name store.
        `store_front` | string |  required  | domain front-end.
        `api_key` | string |  required  | api key store.
        `secret_key` | string |  required  | secret key store.
        `customer_id` | integer |  required  | customer id.
    
<!-- END_3448340d4059d38a6ecb6459d133895d -->

<!-- START_1a117578517f3693d6fa2b402b9d269f -->
## Delete a store

> Example request:

```bash
curl -X DELETE \
    "http://localhost/api/stores/libero" \
    -H "Content-Type: application/json" \
    -H "Accept: application/json"
```

```javascript
const url = new URL(
    "http://localhost/api/stores/libero"
);

let headers = {
    "Content-Type": "application/json",
    "Accept": "application/json",
};

fetch(url, {
    method: "DELETE",
    headers: headers,
})
    .then(response => response.json())
    .then(json => console.log(json));
```



### HTTP Request
`DELETE api/stores/{store}`

#### URL Parameters

Parameter | Status | Description
--------- | ------- | ------- | -------
    `store` |  required  | is ID of the store.

<!-- END_1a117578517f3693d6fa2b402b9d269f -->


