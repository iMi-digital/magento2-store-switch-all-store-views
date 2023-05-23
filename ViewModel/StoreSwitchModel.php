<?php

namespace IMI\StoreSwitch\ViewModel;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Locale\TranslatedLists;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ResourceModel\Website\Collection as WebsiteCollection;
use Magento\Store\Model\ResourceModel\Website\CollectionFactory as WebsiteCollectionFactory;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManager;
use Magento\UrlRewrite\Model\StoreSwitcher\RewriteUrl;

class StoreSwitchModel implements ArgumentInterface
{
    const MODULE_ENABLED_CONFIG_PATH = 'imi_store_switch/general/enable';

    const MODULE_SHOW_COUNTRY_ONLY_CONFIG_PATH = 'imi_store_switch/general/show_country_only';

    const LOCALE_CONFIG_PATH = 'general/locale/code';

    const DEFAULT_COUNTRY_CONFIG_PATH = 'general/country/default';

    const AVAILABLE_WEB_SITES_CONFIG_PATH = 'imi_store_switch/general/available_web_sites';

    private const USE_DIRECT_LINKS = 'imi_store_switch/general/use_direct_links';

    protected RewriteUrl $storeSwitcher;

    /**
     * @var WebsiteCollectionFactory
     */
    private $websiteCollectionFactory;

    /**
     * @var ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var TranslatedLists
     */
    private $translatedLists;

    /**
     * @var StoreManager
     */
    private $storeManager;

    public function __construct(
        WebsiteCollectionFactory $websiteCollectionFactory,
        ScopeConfigInterface $scopeConfig,
        TranslatedLists $translatedLists,
        StoreManager $storeManager,
        RewriteUrl $storeSwitcher,
    ) {
        $this->websiteCollectionFactory = $websiteCollectionFactory;
        $this->scopeConfig = $scopeConfig;
        $this->translatedLists = $translatedLists;
        $this->storeManager = $storeManager;
        $this->storeSwitcher = $storeSwitcher;
    }

    /**
     * Return if stores exists where the switch is enabled except current store.
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isEnabledOnOtherStores(): bool
    {
        foreach ($this->getWebsites() as $website) {
            /** @var Store $store */
            foreach ($website->getStores() as $store) {
                if ($this->isEnabled($store) && $this->getCurrentStore()->getId() != $store->getId()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get collection of websites.
     *
     * @return WebsiteCollection
     */
    public function getWebsites(): WebsiteCollection
    {
        $collection = $this->websiteCollectionFactory->create();

        $enabledIds = $this->getEnabledWebsitesForCurrentWebsite();

        if ($enabledIds === null) {
            return $collection;
        }

        return $collection->addIdFilter($enabledIds);
    }

    private function getEnabledWebsitesForCurrentWebsite(): ?array
    {
        $enabledWebsites = $this->scopeConfig->getValue(
            self::AVAILABLE_WEB_SITES_CONFIG_PATH,
            ScopeInterface::SCOPE_WEBSITE
        );

        if ($enabledWebsites === null) {
            return null;
        }

        return explode(',', $enabledWebsites);
    }

    /**
     * Is the module functionality enabled for current or passed store.
     *
     * @param null $store
     *
     * @return bool
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function isEnabled($store = null): bool
    {
        $configValue = $this->scopeConfig->getValue(
            self::MODULE_ENABLED_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $store ?? $this->getCurrentStore()->getId()
        );

        return boolval($configValue);
    }

    /**
     * @return StoreInterface|string|null
     * @throws \Magento\Framework\Exception\NoSuchEntityException
     */
    public function getCurrentStore(): StoreInterface
    {
        return $this->storeManager->getStore();
    }

    /**
     * Get the formatted label for the dropdown, based on the format configuration.
     *
     * @param StoreInterface $store
     *
     * @return string
     * @throws \Exception
     */
    public function getStoreSwitchLabel(StoreInterface $store): string
    {
        $showCountryOnly = $this->scopeConfig->getValue(
            self::MODULE_SHOW_COUNTRY_ONLY_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
        if ($showCountryOnly) {
            return $this->getStoreCountyCode($store);
        }

        return $this->getParsedLanguage($store) . '&nbsp;(' . $this->getStoreCountyCode($store) . ')';
    }

    /**
     * Get country code for given store.
     *
     * @param StoreInterface $store
     *
     * @return string
     */
    public function getStoreCountyCode(StoreInterface $store): string
    {
        return $this->scopeConfig->getValue(
            self::DEFAULT_COUNTRY_CONFIG_PATH,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * Get locale label without the country in brackets.
     *
     * @param StoreInterface $store
     *
     * @return string|string[]|null
     * @throws \Exception
     */
    public function getParsedLanguage(StoreInterface $store): string
    {
        $localeLabel = $this->getStoreLocaleLabel($store);

        return preg_replace('/ \(.*\)/', '', $localeLabel);
    }

    /**
     * Get locale label for given store.
     *
     * @param StoreInterface $store
     *
     * @return string
     * @throws \Exception
     */
    private function getStoreLocaleLabel(StoreInterface $store): string
    {
        $locales = $this->translatedLists->getOptionLocales();
        $storeLocale = $this->getStoreLocale($store);

        foreach ($locales as $locale) {
            if ($locale['value'] === $storeLocale) {
                return $locale['label'];
            }
        }
        throw new \Exception('No matching locale found. Store id: ' . $store->getId());
    }

    /**
     * Get locale for given store.
     *
     * @param StoreInterface $store
     *
     * @return string
     */
    private function getStoreLocale(StoreInterface $store): string
    {
        return $this->scopeConfig->getValue(self::LOCALE_CONFIG_PATH, ScopeInterface::SCOPE_STORE, $store->getId());
    }

    public function useDirectLinks(): bool
    {
        return boolval($this->scopeConfig->getValue(self::USE_DIRECT_LINKS, ScopeInterface::SCOPE_STORES));
    }

    public function getDirectLink(Store $_store, string $currentUrl): string
    {
        $newUrl = $this->storeSwitcher->switch($this->storeManager->getStore(), $_store, $currentUrl);

        if ($newUrl === $currentUrl) {
            return $_store->getBaseUrl();
        }

        return $newUrl;
    }
}
