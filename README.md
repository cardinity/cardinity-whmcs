# Cardinity Payment Gateway for WHMCS

This module will enable Cardinity payments in your WHMCS shop.

## Requirements
* [Cardinity account](https://cardinity.com)
* WHMCS v7.4.2 or above
* [Composer](https://getcomposer.org)

## Installation
1. Download the latest release
2. Open the directory and enter `cardinity` folder
3. Run `composer install` to install Cardinity SDK package
4. Copy `modules` folder to `<whmcs_dir>`
5. Open WHMCS Admin area
6. Navigate to  `Setup -> Payments -> Payment Gateways`
7. Click the `All Payment Gateways` tab
8. Click `Cardinity`
9. Enter your API keys from Cardinity members area

## Installation above version 8
* Follow Steps 1 - 5 as above
6. Navigate to "Wrench" on upper right corner `Configuration`  > `System Settings`
7. Find `Payment Gateways`
8. Click the `All Payment Gateways` tab
9. Click `Cardinity`
10. Enter required details on the `Manage Existing Gateways` tab