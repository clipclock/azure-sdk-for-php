<?php

/**
 * LICENSE: Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 * 
 * PHP version 5
 *
 * @category  Microsoft
 * @package   WindowsAzure\ServiceBus\Models
 * @author    Azure PHP SDK <azurephpsdk@microsoft.com>
 * @copyright 2012 Microsoft Corporation
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @link      http://pear.php.net/package/azure-sdk-for-php
 */

namespace WindowsAzure\ServiceBus\Models;

use WindowsAzure\Common\Internal\Atom\Feed;
use WindowsAzure\Common\Internal\Atom\Content;
use WindowsAzure\ServiceBus\Models\SubscriptionInfo;

/**
 * The result of the list subscription request.
 *
 * @category  Microsoft
 * @package   WindowsAzure\ServiceBus\Models
 * @author    Azure PHP SDK <azurephpsdk@microsoft.com>
 * @copyright 2012 Microsoft Corporation
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/azure-sdk-for-php
 */

class ListSubscriptionsResult extends Feed
{
    /**
     * The information of the subscription. 
     * 
     * @var array
     */
    private $_subscriptionInfo;

    /**
     * Populates the properties with a the response from the list subscriptions request.
     * 
     * @param string $response The body of the response of the list subscriptions request. 
     */
    public function parseXml($response)
    {
        parent::parseXml($response);
        $listSubscriptionsResultXml = new \SimpleXMLElement($response);
        $this->_subscriptionInfo = array();
        foreach ($listSubscriptionsResultXml->entry as $entry)
        {
            $subscriptionInfo = new SubscriptionInfo();
            $subscriptionInfo->parseXml($entry->asXml());
            $this->_subscriptionInfo[] = $subscriptionInfo;
        }
    }

    /**
     * Creates a list subscriptions result with default parameters. 
     */
    public function __construct()
    {
    }
    
    /**
     * Gets the information of the subscription. 
     * 
     * @return array
     */
    public function getSubscriptionInfo()
    {
        return $this->_subscriptionInfo;
    }

    /**
     * Sets the information of the rule. 
     * 
     * @param array $subscriptionInfo The information of the
     * subscription.
     */
    public function setSubscriptionInfo($subscriptionInfo)
    {
        $this->_subscriptionInfo = $subscriptionInfo;
    }

}
?>