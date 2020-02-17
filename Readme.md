# IMI StoreSwitch

This module extends the core store switch `Magento\Store\Block\Switcher`.  
By passing `\IMI\StoreSwitch\ViewModel\StoreSwitchModel` as an argument to the Switcher Block and using the `IMI_StoreSwitch::switch/languages.phtml` template you can switch between all stores of all websites.

It is possible to enable or disabled the switcher in default, website and store scope. The corresponding acl resource is `IMI_StoreSwitch::config`.

The switcher is disabled by default, it can be enabled with the config value `imi_store_switch/general/enable` or in 
the admin configuration on `Stores > iMi > Store Switch`.

There is also an option to only show the country code in the store view.
If enabled, the available options will only display the country code, otherwise the name and country code are shown.

# License

© 2020 iMi digital GmbH. Licensed under [MIT](LICENSE).
