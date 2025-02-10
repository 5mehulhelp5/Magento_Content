<?php
use Magento\Framework\App\Bootstrap;

require __DIR__ . '/../app/bootstrap.php';

$params = $_SERVER;
$bootstrap = Bootstrap::create(BP, $params);
$obj = $bootstrap->getObjectManager();

// Set the area code (required for blocks that depend on frontend/adminhtml area)
$appState = $obj->get(\Magento\Framework\App\State::class);
try {
    $appState->setAreaCode('adminhtml'); // Use 'frontend' if debugging frontend blocks
} catch (\Magento\Framework\Exception\LocalizedException $e) {
    // Area code might already be set, ignore the exception
}

// Replace with your block class
$blockClass = \Aventure7\Admintheme\Block\Adminhtml\Logo::class;

/** @var \Magento\Framework\View\Element\Template $block */
$block = $obj->create($blockClass);

// Call the method to debug
$method = 'getAdminDefaultLogo'; // Replace with your actual method
$result = method_exists($block, $method) ? $block->$method() : 'Method does not exist';

echo '<pre>';
print_r($result);
echo '</pre>';
