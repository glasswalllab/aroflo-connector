# AroFlo PHP Wrapper
 
This package provides an integration to AroFlo (https://aroflo.com/).

API Docs - https://apidocs.aroflo.com/

[![Latest Version](https://img.shields.io/github/release/glasswalllab/aroflo-connector.svg?style=flat-square)](https://github.com/glasswalllab/aroflo-connector/releases)

## Installation

You can install the package via composer:

```bash
composer require glasswalllab/arofloconnector 
```

## Usage

1. Setup API Application in your AroFlo account (Site Administration -> Settings -> AroFlo API)

2. Include the following variables in your .env

```
AROFLO_UENCODE=YOUR_uENCODE
AROFLO_KEYID=YOUR_pENCODE
AROFLO_SECRET=YOUR_API_SECRET
AROFLO_ORGENCODE=YOUR_orgENCODE
AROFLO_BASE_API_URL=https://api.aroflo.com/
```

3. Run **php artisan migrate** to create the api_logs database table

### Sample Usage (Laravel)

```php
use glasswalllab\arofloconnector\ArofloConnector;

//Get Task Types - with joins and were clauses
$aroflo = new ArofloConnector();
$joins = array('locations,locationcustomfields');
$wheres = array('and|archived|=|false');

//CallAroflo($zone, $joins, $wheres, $postxml, $method, $page)
$aroflo->CallAroflo('tasktypes', $joins, $wheres, '', 'GET',1)

//Post new client
$aroflo = new ArofloConnector();
$org = YOUR_orgENCODE
$postxml = '<clients><client><clientname><![CDATA[ Testing Client ]]></clientname><firstname><![CDATA[ Testing ]]></firstname><surname><![CDATA[ Client ]]></surname><phone>0412345678</phone><mobile>0412345678</mobile><email><![CDATA[ sreid@gwlab.com.au ]]></email><orgs><org><orgid>'.$org.'</orgid></org></orgs><address><addressline1><![CDATA[ 1 Smith Street ]]></addressline1><addressline2></addressline2><suburb></suburb><state><![CDATA[ Tasmania ]]></state><postcode><![CDATA[ 7000 ]]></postcode><country><![CDATA[ Australia ]]></country></address><gpsautogenerate>TRUE</gpsautogenerate><mailingaddress><addressline1><![CDATA[ 1 Smith Street ]]></addressline1><addressline2></addressline2><suburb></suburb><state><![CDATA[ Tasmania ]]></state><postcode>70000</postcode><country><![CDATA[ Australia ]]></country></mailingaddress></client></clients>';

//CallAroflo($zone, $joins, $wheres, $postxml, $method, $page)
$aroflo->CallAroflo('clients', [], [], $postxml, 'POST',null);

```

### Security

If you discover any security related issues, please email sreid@gwlab.com.au instead of using the issue tracker.