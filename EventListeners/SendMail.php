<?php
/*************************************************************************************/
/*      This file is part of the Thelia package.                                     */
/*                                                                                   */
/*      Copyright (c) OpenStudio                                                     */
/*      email : dev@thelia.net                                                       */
/*      web : http://www.thelia.net                                                  */
/*                                                                                   */
/*      For the full copyright and license information, please view the LICENSE.txt  */
/*      file that was distributed with this source code.                             */
/*************************************************************************************/

namespace Predict\EventListeners;

use Predict\Predict;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Thelia\Core\Event\Order\OrderEvent;
use Thelia\Core\Event\TheliaEvents;
use Thelia\Core\Template\ParserInterface;
use Thelia\Core\Translation\Translator;
use Thelia\Log\Tlog;
use Thelia\Mailer\MailerFactory;
use Thelia\Model\ConfigQuery;
use Thelia\Model\MessageQuery;

/**
 * Class SendMail
 * @package Predict\EventListener
 * @author Benjamin Perche <bperche@openstudio.fr>
 */
class SendMail implements EventSubscriberInterface
{

    protected $parser;

    protected $mailer;

    public function __construct(ParserInterface $parser, MailerFactory $mailer)
    {
        $this->parser = $parser;
        $this->mailer = $mailer;
    }

    public function updateStatus(OrderEvent $event)
    {
        $order = $event->getOrder();
        $Predict = new Predict();

        if ($order->isSent() && $order->getDeliveryModuleId() == $Predict->getModuleModel()->getId()) {
            $contact_email = ConfigQuery::read('store_email');

            if ($contact_email) {

                $message = MessageQuery::create()
                    ->filterByName('mail_predict')
                    ->findOne();

                if (false === $message) {
                    throw new \Exception(
                        Translator::getInstance()->trans(
                            "Failed to load message '%mail_tpl_name'.",
                            [
                                "%mail_tpl_name" => "mail_predict",
                            ],
                            Predict::MESSAGE_DOMAIN
                        )
                    );
                }

                $order = $event->getOrder();
                $customer = $order->getCustomer();

                $this->mailer->sendEmailToCustomer(
                    'mail_predict',
                    $customer,
                    [
                        'order_id' => $order->getId(),
                        'order_ref' => $order->getRef(),
                        'order_date' => $order->getCreatedAt(),
                        'update_date' => $order->getUpdatedAt(),
                        'package' => $order->getDeliveryRef(),
                    ]
                );

                Tlog::getInstance()->debug("Predict shipping message sent to customer ".$customer->getEmail());
            } else {
              $customer = $order->getCustomer();
              Tlog::getInstance()->debug("Predict shipping message no contact email customer_id ".$customer->getId());
            }
        }
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * The array keys are event names and the value can be:
     *
     *  * The method name to call (priority defaults to 0)
     *  * An array composed of the method name to call and the priority
     *  * An array of arrays composed of the method names to call and respective
     *    priorities, or 0 if unset
     *
     * For instance:
     *
     *  * array('eventName' => 'methodName')
     *  * array('eventName' => array('methodName', $priority))
     *  * array('eventName' => array(array('methodName1', $priority), array('methodName2'))
     *
     * @return array The event names to listen to
     *
     * @api
     */
    public static function getSubscribedEvents()
    {
        return array(
            TheliaEvents::ORDER_UPDATE_STATUS => array("updateStatus", 50)
        );
    }
}
