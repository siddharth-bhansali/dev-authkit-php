# authkit-php

Secure token generation for [IntegrationOS AuthKit](https://docs.integrationos.com/docs/authkit)
using [Composer](https://getcomposer.org/).

## Install

With composer:

```bash
composer require integration-os/authkit-php 
```

## Creating a token endpoint

You'll want to create an internal endpoint used to generate secure tokens for your frontend. You can do so by
adding code that looks like the below snippet. You can then call the file directly in the browser or using a tool like
curl or Postman by making a POST request to http://yourserver.com/endpoint.php.

```php
<?php  // file endpoint.php

require "vendor/autoload.php";

use IntegrationOS\AuthKit\AuthKit;

$authkit = new AuthKit("sk_live_12345");
$response = $authkit->create([
	"group" => "meaningful-id", // a meaningful identifier (i.e., organizationId)
	"label" => "Friendly Label", // a human-friendly label (i.e., organizationName)
]);

echo json_encode($response);
```

Or if you're using Laravel, you can define a route like below:

```php
<?php

use Illuminate\Support\Facades\Route;
use IntegrationOS\AuthKit\AuthKit;

Route::get('/create-embed-token', function () {
    $authkit = new AuthKit("sk_live_12345");
	
    $response = $authkit->create([
        "group" => "meaningful-id", // a meaningful identifier (i.e., organizationId)
        "label" => "Friendly Label", // a human-friendly label (i.e., organizationName)
    ]);
    
    return $response;
});
```

You'll want to switch out the API Key for your own, which will later tell your frontend which integrations you'd like to
make available to your users.

You'll also want to populate the `Group` and `Label` fields depending on how you want to organize and query your users'
connected accounts. The Group is especially important as it's used to generate the
unique [Connection Key](https://docs.integrationos.com/docs/setup) for the user once they successfully connect an
account.

## Full Documentation

Please refer to the official [IntegrationOS AuthKit](https://docs.integrationos.com/docs/authkit) docs for a more
holistic understanding of IntegrationOS AuthKit.