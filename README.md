# Mobbex for Vnecoms Marketplace

## Requisitos
* PHP >= 7.1
* Magento >= 2.1.0
* Vnecoms Marketplace >= 2.1.0
* Mobbex for Magento 2 >= 2.1.2

## Instalación 
> Asegurese de que todos los módulos de Vnecoms Marketplace estén instalados antes de comenzar la integración con Mobbex.

1. **Si está utilizando Composer 1,** primero agregue el repositorio:
    ```
    composer config repositories.mobbexco-magento-2-marketplace vcs https://github.com/mobbexco/magento-2-marketplace
    ```

2. Descargue el paquete:
    ```
    composer require mobbexco/magento-2-marketplace
    ```

3. Asegurese de que los módulos estén activados:
    ```
    php bin/magento module:enable Mobbex_Webpay Mobbex_Marketplace
    ```

4. Actualice la base de datos y regenere los archivos:
    ```
    php bin/magento setup:upgrade
    php bin/magento setup:static-content:deploy -f
    ```

5. Añada las credenciales de Mobbex al módulo desde el panel de administración.