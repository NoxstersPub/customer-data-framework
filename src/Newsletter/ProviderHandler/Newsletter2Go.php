<?php
/**
 * Created by PhpStorm.
 * User: tmittendorfer
 * Date: 10.07.2018
 * Time: 13:16
 */

namespace CustomerManagementFrameworkBundle\Newsletter\ProviderHandler;


use CustomerManagementFrameworkBundle\Model\NewsletterAwareCustomerInterface;
use CustomerManagementFrameworkBundle\Newsletter\ProviderHandler\Newsletter2Go\Newsletter2GoExportService;
use CustomerManagementFrameworkBundle\Newsletter\Queue\Item\DefaultNewsletterQueueItem;
use CustomerManagementFrameworkBundle\Newsletter\Queue\Item\NewsletterQueueItemInterface;
use CustomerManagementFrameworkBundle\Newsletter\Queue\NewsletterQueueInterface;
use CustomerManagementFrameworkBundle\Traits\LoggerAware;

class Newsletter2Go implements NewsletterProviderHandlerInterface
{
    use LoggerAware;


    /**
     * @var string
     */
    protected $shortcut;

    /**
     * @var string
     */
    protected $listId;

    /**
     * @var Newsletter2GoExportService
     */
    protected $exportService;


    /**
     * @var array $statusMapping
     * @var array $reverseStatusMapping
     */
    protected $statusMapping;
    protected $reverseStatusMapping;


    public function __construct($shortcut, $listId, array $statusMapping = [], array $reverseStatusMapping = [], Newsletter2Go\Newsletter2GoExportService $exportService)
    {
        $this->shortcut = $shortcut;
        $this->listId = $listId;

        $this->exportService = $exportService;

        $this->statusMapping = $statusMapping;
        $this->reverseStatusMapping = $reverseStatusMapping;
    }

    /**
     * Returns a unique identifier/short name of the provider handler.
     *
     * @return string
     */
    public function getShortcut()
    {
        return $this->shortcut;
    }

    /**
     * Update given NewsletterQueueItems in newsletter provider.
     * Needs to set $item->setSuccsessfullyProcessed(true) if it was successfull otherwise the item will never be removed from the newsletter queue.
     *
     * @param NewsletterQueueItemInterface[] $items
     * @param bool $forceUpdate
     *
     * @return void
     */
    public function processCustomerQueueItems(array $items, $forceUpdate = false)
    {
        $this->getLogger()->info('newsletter 2 go customer queue process started');
        var_dump(count($items));
        $this->exportService->exportMultiple($items, $this);
    }

    /**
     * @param bool $forceUpdate
     *
     * @return void
     */
    public function updateSegmentGroups($forceUpdate = false)
    {
        // TODO: Implement updateSegmentGroups() method.
    }

    /**
     * Subscribe customer to newsletter (for example via web form). Returns true if it was successful.
     *
     * @param NewsletterAwareCustomerInterface $customer
     *
     * @return bool
     */
    public function subscribeCustomer(NewsletterAwareCustomerInterface $customer)
    {
        if($customer->needsExportByNewsletterProviderHandler($this)) {


            $item = new DefaultNewsletterQueueItem(
                $customer->getId(),
                $customer,
                $customer->getEmail(),
                NewsletterQueueInterface::OPERATION_UPDATE
            );

            //todo check for errors
            $this->exportService->update($item, $this);
        }


        return true;
    }

    /**
     * deletes the customer from newsletter to go
     *
     * @param NewsletterAwareCustomerInterface $customer
     *
     * @return bool
     */
    public function unsubscribeCustomer(NewsletterAwareCustomerInterface $customer)
    {
        if($customer->needsExportByNewsletterProviderHandler($this)) {

            $item = new DefaultNewsletterQueueItem(
                $customer->getId(),
                $customer,
                $customer->getEmail(),
                NewsletterQueueInterface::OPERATION_DELETE
            );

            //todo check for errors
            $this->exportService->delete($item, $this);

            return true;
        }
    }


    /**
     * sets status to pending and sends the double opt in mail
     *
     * @param NewsletterAwareCustomerInterface $customer
     */
    public function registerCustomer(NewsletterAwareCustomerInterface $customer) {

        $item = new DefaultNewsletterQueueItem(
            $customer->getId(),
            $customer,
            $customer->getEmail(),
            NewsletterQueueInterface::OPERATION_UPDATE
        );

        if($this->exportService->register($item, $this)) {
        }else {
            //error
        }

    }

    public function getExternalData(NewsletterAwareCustomerInterface $customer) {
        return $this->exportService->getExternalData($customer, $this);
    }



    public function getListId() {
        return $this->listId;
    }

    public function getDoubleOptInFormCode() {
        return 'reo1gmeb-vnkyt9av-bsi';
    }










    public function getNewsletterStatusFieldName() {
        return 'newsletterStatus' . ucfirst($this->getShortcut());
    }
    public function getNewsletter2GoStatusFieldName() {
        return 'newsletter2goStatus' . ucfirst($this->getShortcut());
    }
    public function setNewsletterStatus(NewsletterAwareCustomerInterface $customer, $status)
    {
        $setter = 'set'. $this->getNewsletterStatusFieldName();
        if (!method_exists($customer, $setter)) {
            throw new \Exception(sprintf(
                'Customer needs to have a field %s in order to be able to hold the newsletter status for newsletter provider handler with shortcut %s',
                $setter,
                $this->getShortcut()
            ));
        }
        $customer->$setter($status);
    }
    public function getNewsletterStatus(NewsletterAwareCustomerInterface $customer)
    {
        $getter = 'get' . $this->getNewsletterStatusFieldName();

        if (!method_exists($customer, $getter)) {
            throw new \Exception(sprintf(
                'Customer needs to have a field %s in order to be able to hold the newsletter status for newsletter provider handler with shortcut %s',
                $getter,
                $this->getShortcut()
            ));
        }
        return $customer->$getter();
    }

    public function updateNewsletterStatus(NewsletterAwareCustomerInterface $customer, $status, $saveCustomer = true)
    {
        $getter = 'get' . $this->getNewsletterStatusFieldName();
        // status did not changed => no customer save needed
        if ($customer->$getter() == $status) {
            return;
        }
        $this->setNewsletterStatus($customer, $status);
        if ($saveCustomer) {
            /* The newsletter queue needs to be disabled to avoid endless loops.
               Some other components are disabled for performance reasons as they are not needed here.
               If somebody ever wants to build segments based on the mailchimp status then they could be handled via the segment building queue.
             */
            $customer->saveWithOptions(
                $customer->getSaveManager()->getSaveOptions(true)
                    ->disableNewsletterQueue()
                    ->disableOnSaveSegmentBuilders()
                    ->disableValidator()
                    ->disableDuplicatesIndex()
            );
        }
    }



    public function setNewsletter2GoStatus(NewsletterAwareCustomerInterface $customer, $status)
    {
        $setter = 'set'. $this->getNewsletter2GoStatusFieldName();
        if (!method_exists($customer, $setter)) {
            throw new \Exception(sprintf(
                'Customer needs to have a field %s in order to be able to hold the Newsletter2go status for newsletter provider handler with shortcut %s',
                $setter,
                $this->getShortcut()
            ));
        }
        $customer->$setter($status);
    }

    public function getNewsletter2GoStatus(NewsletterAwareCustomerInterface $customer)
    {
        $getter = 'get' . $this->getNewsletter2GoStatusFieldName();

        if (!method_exists($customer, $getter)) {
            throw new \Exception(sprintf(
                'Customer needs to have a field %s in order to be able to hold the Newsletter2go status for newsletter provider handler with shortcut %s',
                $getter,
                $this->getShortcut()
            ));
        }

        return $customer->$getter();
    }

    public function updateNewsletter2GoStatus(NewsletterAwareCustomerInterface $customer, $status, $saveCustomer = true)
    {
        $getter = 'get' . $this->getNewsletter2GoStatusFieldName();
        // status did not changed => no customer save needed
        if ($customer->$getter() == $status) {
            return;
        }
        $this->setNewsletter2GoStatus($customer, $status);
        if ($saveCustomer) {
            /* The newsletter queue needs to be disabled to avoid endless loops.
               Some other components are disabled for performance reasons as they are not needed here.
               If somebody ever wants to build segments based on the mailchimp status then they could be handled via the segment building queue.
             */
            $customer->saveWithOptions(
                $customer->getSaveManager()->getSaveOptions(true)
                    ->disableNewsletterQueue()
                    ->disableOnSaveSegmentBuilders()
                    ->disableValidator()
                    ->disableDuplicatesIndex()
            );
        }
    }



    public function reverseMapNewsletterStatus($newsletter2goStatus)
    {
        if (isset($this->reverseStatusMapping[$newsletter2goStatus])) {
            return $this->reverseStatusMapping[$newsletter2goStatus];
        }

        return null;
    }

    public function mapNewsletterStatus($newsletterStatus)
    {
        if (isset($this->statusMapping[$newsletterStatus])) {
            return $this->statusMapping[$newsletterStatus];
        }

        return null;
    }
}
