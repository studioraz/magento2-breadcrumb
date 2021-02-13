# Studio Raz Magento 2 for Breadcrumbs

## Features
1. Renders breadcrumbs full path
  
## Installation

1. To access Studio Raz *private* packages in Composer, configure authentication for the project:  
    ```
    composer config --auth http-basic.repo.packagist.com <username> <password>
    ```
2. Set up the custom repository with the following command
    ```
    composer config repositories.private-packagist composer https://repo.packagist.com/studioraz/
    composer config repositories.packagist.org false
    ```

3. Install this module within Magento 2 using composer
    ```
    composer require studioraz/magento2-breadcrumb
    ```

4. After this, enable the module as usual
    ```
    bin/magento mo:e SR_Breadcrumb &&
    bin/magento s:up
    ```
 
 

