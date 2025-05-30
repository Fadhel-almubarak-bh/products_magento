<?php
namespace Fadhel\Redirect\Controller\Route;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RedirectFactory;

class Index extends Action
{
    /**
     * @var RedirectFactory
     */
    protected $resultRedirectFactory;

    /**
     * @param Context $context
     * @param RedirectFactory $resultRedirectFactory
     */
    public function __construct(
        Context $context,
        RedirectFactory $resultRedirectFactory
    ) {
        $this->resultRedirectFactory = $resultRedirectFactory;
        parent::__construct($context);
    }

    /**
     * Redirect to men's hoodies and sweatshirts page
     *
     * @return \Magento\Framework\Controller\Result\Redirect
     */
    public function execute()
    {
        $resultRedirect = $this->resultRedirectFactory->create();
        return $resultRedirect->setPath('men/tops-men/hoodies-and-sweatshirts-men.html');
    }
} 