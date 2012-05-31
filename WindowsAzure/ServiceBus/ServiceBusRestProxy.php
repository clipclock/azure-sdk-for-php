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
 * @package   WindowsAzure\ServiceBus
 * @author    Azure PHP SDK <azurephpsdk@microsoft.com>
 * @copyright 2012 Microsoft Corporation
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @link      http://pear.php.net/package/azure-sdk-for-php
 */

namespace WindowsAzure\ServiceBus;
use WindowsAzure\Common\Internal\ServiceRestProxy;
use WindowsAzure\Common\Internal\Http\HttpCallContext;
use WindowsAzure\Common\Internal\Serialization\XmlSerializer;
use WindowsAzure\Common\Internal\Atom\Content;
use WindowsAzure\Common\Internal\Atom\Entry;
use WindowsAzure\Common\Internal\Atom\Feed;
use WindowsAzure\Common\ServiceException;
use WindowsAzure\ServiceBus\Internal\IServiceBus;
use WindowsAzure\ServiceBus\Models\BrokeredMessage;
use WindowsAzure\ServiceBus\Models\BrokerProperties;
use WindowsAzure\ServiceBus\Models\CreateQueueResult;
use WindowsAzure\ServiceBus\Models\CreateRuleResult;
use WindowsAzure\ServiceBus\Models\CreateTopicResult;
use WindowsAzure\ServiceBus\Models\CreateSubscriptionResult;
use WindowsAzure\ServiceBus\Models\GetQueueResult;
use WindowsAzure\ServiceBus\Models\GetRuleResult;
use WindowsAzure\ServiceBus\Models\GetSubscriptionResult;
use WindowsAzure\ServiceBus\Models\GetTopicResult;
use WindowsAzure\ServiceBus\Models\ListQueuesOptions;
use WindowsAzure\ServiceBus\Models\ListQueuesResult;
use WindowsAzure\ServiceBus\Models\ListSubscriptionsOptions;
use WindowsAzure\ServiceBus\Models\ListSubscriptionsResult;
use WindowsAzure\ServiceBus\Models\ListTopicsOptions;
use WindowsAzure\ServiceBus\Models\ListTopicsResult;
use WindowsAzure\ServiceBus\Models\ListRulesOptions;
use WindowsAzure\ServiceBus\Models\ListRulesResult;
use WindowsAzure\ServiceBus\Models\ListOptions;
use WindowsAzure\ServiceBus\Models\QueueDescription;
use WindowsAzure\ServiceBus\Models\QueueInfo;
use WindowsAzure\ServiceBus\Models\RuleDescription;
use WindowsAzure\ServiceBus\Models\RuleInfo;
use WindowsAzure\ServiceBus\Models\SubscriptionDescription;
use WindowsAzure\ServiceBus\Models\SubscriptionInfo;
use WindowsAzure\ServiceBus\Models\TopicDescription;
use WindowsAzure\ServiceBus\Models\TopicInfo;
use WindowsAzure\Common\Internal\Resources;
use WindowsAzure\Common\Internal\Utilities;
use WindowsAzure\Common\Internal\Validate;

/**
 * This class constructs HTTP requests and receive HTTP responses 
 * for service bus.
 *
 * @category  Microsoft
 * @package   WindowsAzure\ServiceBus
 * @author    Azure PHP SDK <azurephpsdk@microsoft.com>
 * @copyright 2012 Microsoft Corporation
 * @license   http://www.apache.org/licenses/LICENSE-2.0  Apache License 2.0
 * @version   Release: @package_version@
 * @link      http://pear.php.net/package/azure-sdk-for-php
 */

class ServiceBusRestProxy extends ServiceRestProxy implements IServiceBus
{
    /**
     * Creates a ServiceBusRestProxy with specified parameter. 
     * 
     * @param IHttpClient $channel        The channel to communicate. 
     * @param string      $uri            The URI of service bus service.
     * @param ISerializer $dataSerializer The serializer of the service bus.
     *
     * @return none
     */
    public function __construct($channel, $uri, $dataSerializer)
    {
        parent::__construct($channel, $uri, '', $dataSerializer);
    }
    
    /**
     * Sends a brokered message. 
     * 
     * @param type $path            The path to send message. 
     * @param type $brokeredMessage The brokered message. 
     *
     * @return none
     */
    public function sendMessage($path, $brokeredMessage)
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_POST);
        $httpCallContext->addStatusCode(Resources::STATUS_CREATED);
        $httpCallContext->setPath($path);
        $contentType = $brokeredMessage->getContentType();

        if (!is_null($contentType))
        {
            $httpCallContext->addHeader(
                Resources::CONTENT_TYPE,
                $contentType
            );
        }
        
        $brokerProperties = $brokeredMessage->getBrokerProperties();
        if (!is_null($brokerProperties))
        {
            $httpCallContext->addHeader(
                Resources::BROKER_PROPERTIES,
                $brokerProperties->toString()
            );
        } 
        $customProperties = $brokeredMessage->getProperties();

        if (!empty($customProperties))
        {
            foreach ($customProperties as $key => $value)
            {
                $httpCallContext->addHeader($key, $value);
                    
            }
        }

        $httpCallContext->setBody($brokeredMessage->getBody());
        $this->sendContext($httpCallContext);
    }

    /**
     * Sends a queue message. 
     * 
     * @param string          $queueName       The name of the queue.
     * @param BrokeredMessage $brokeredMessage The brokered message. 
     *
     * @return none
     */
    public function sendQueueMessage($queueName, $brokeredMessage)
    {
        $path = sprintf(Resources::SEND_MESSAGE_PATH, $queueName);
        $this->sendMessage($path, $brokeredMessage);
    }
    
    /**
     * Receives a queue message. 
     * 
     * @param string                $queueName             The name of the
     * queue. 
     * @param ReceiveMessageOptions $receiveMessageOptions The options to 
     * receive the message. 
     *
     * @return BrokeredMessage
     */
    public function receiveQueueMessage($queuePath, $receiveMessageOptions)
    {
        $queueMessagePath = sprintf(Resources::RECEIVE_MESSAGE_PATH, $queuePath);
        return $this->receiveMessage(
            $queueMessagePath, 
            $receiveMessageOptions
        );
    }

    /**
     * Receives a message. 
     * 
     * @param string                 $path                  The path of the 
     * message. 
     * @param ReceivedMessageOptions $receiveMessageOptions The options to 
     * receive the message. 
     *
     * @return BrokeredMessage
     */
    public function receiveMessage($path, $receiveMessageOptions)
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setPath($path);
        $httpCallContext->addStatusCode(Resources::STATUS_CREATED);
        $httpCallContext->addStatusCode(Resources::STATUS_NO_CONTENT);
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $timeout = $receiveMessageOptions->getTimeout();
        if (!is_null($timeout))
        {
            $httpCallContext->addQueryParameter('timeout', $timeout);
        }

        if ($receiveMessageOptions->getIsReceiveAndDelete()) {
            $httpCallContext->setMethod(Resources::HTTP_DELETE);
        }
        else if ($receiveMessageOptions->getIsPeekLock()) {
            $httpCallContext->setMethod(Resources::HTTP_POST);
        }
        else {
            throw new ServiceException(
                'The receive message option is in an unknown mode.'
            );
        }

        $response = $this->sendContext($httpCallContext);
        $responseHeaders = $response->getHeader(); 
        $brokerProperties = new BrokerProperties();

        if (array_key_exists('brokerproperties', $responseHeaders))
        {
            $brokerProperties = BrokerProperties::create(
                $responseHeaders['brokerproperties']
            );
        }

        if (array_key_exists('location', $responseHeaders))
        {
            $brokerProperties->setLockLocation($responseHeaders['location']);
        }

        $brokeredMessage = new BrokeredMessage($brokerProperties);
        
        if (array_key_exists(Resources::CONTENT_TYPE, $responseHeaders))
        {
            $brokeredMessage->setContentType($responseHeaders[Resources::CONTENT_TYPE]);
        }

        if (array_key_exists('Date', $responseHeaders))
        {
            $brokeredMessage->setDate($responseHeaders['Date']);
        }

        $brokeredMessage->setBody($response->getBody());

        foreach (array_keys($responseHeaders) as $headerKey)
        {
            $brokeredMessage->setProperty(
                $headerKey, 
                $responseHeaders[$headerKey]
            );
        }

        return $brokeredMessage; 
    }

    /**
     * Sends a brokered message to a specified topic. 
     * 
     * @param string          $topicName       The name of the topic. 
     * @param BrokeredMessage $brokeredMessage The brokered message. 
     *
     * @return none
     */
    public function sendTopicMessage($topicName, $brokeredMessage)
    {
        $topicMessagePath =
            sprintf(Resources::SEND_MESSAGE_PATH, $topicName);
        $this->sendMessage($topicMessagePath, $brokeredMessage);
    } 

    /**
     * Receives a subscription message. 
     * 
     * @param string                $topicName             The name of the 
     * topic.
     * @param string                $subscriptionName      The name of the 
     * subscription.
     * @param ReceiveMessageOptions $receiveMessageOptions The options to 
     * receive the subscription message. 
     *
     * @return BrokeredMessage 
     */
    public function receiveSubscriptionMessage(
        $topicName, 
        $subscriptionName, 
        $receiveMessageOptions
    ) {
        $messagePath = sprintf(
            Resources::RECEIVE_SUBSCRIPTION_MESSAGE_PATH, 
            $topicName,
            $subscriptionName
        );

        $brokeredMessage = $this->receiveMessage(
            $messagePath,
            $receiveMessageOptions
        );

        return $brokeredMessage;
    }

    /**
     * Unlocks a brokered message. 
     * 
     * @param BrokeredMessage $brokeredMessage The brokered message. 
     *
     * @return none
     */
    public function unlockMessage($brokeredMessage)
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_PUT);
        $lockLocation = $brokeredMessage->getLockLocation();
        $lockLocationArray = parse_url($lockLocation);
        $lockLocationPath = '';

        if (array_key_exists(Resources::PHP_URL_PATH, $lockLocationArray))
        {
            $lockLocationPath = $lockLocationArray[Resources::PHP_URL_PATH];
            $lockLocationPath = preg_replace('@^\/@', '', $lockLocationPath);
        } 

        $httpCallContext->setPath($lockLocationPath);
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $this->sendContext($httpCallContext);
    }
    
    /**
     * Deletes a brokered message. 
     * 
     * @param BrokeredMessage $brokeredMessage The borkered message.
     *
     * @return none
     */
    public function deleteMessage($brokeredMessage)
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_DELETE);
        $lockLocation = $brokeredMessage->getLockLocation();
        $lockLocationArray = parse_url($lockLocation);
        $lockLocationPath = '';

        if (array_key_exists(Resources::PHP_URL_PATH, $lockLocationArray))
        {
            $lockLocationPath = $lockLocationArray[Resources::PHP_URL_PATH];
            $lockLocationPath = preg_replace('@^\/@', '', $lockLocationPath);
        } 
        $httpCallContext->setPath($lockLocationPath);
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $this->sendContext($httpCallContext);
    }
   
    /**
     * Creates a queue with a specified queue information. 
     * 
     * @param QueueInfo $queueInfo The information of the queue.
     *
     * @return CreateQueueResult
     */
    public function createQueue($queueInfo)
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_PUT);
        $httpCallContext->setPath($queueInfo->getTitle());
        $httpCallContext->addHeader(
            Resources::CONTENT_TYPE,
            Resources::ATOM_ENTRY_CONTENT_TYPE
        );
        $httpCallContext->addStatusCode(Resources::STATUS_CREATED);
        
        $queueDescriptionXml = XmlSerializer::objectSerialize(
            $queueInfo->getQueueDescription(),
            'QueueDescription'
        );

        $entry = new Entry();
        $content = new Content($queueDescriptionXml);
        $content->setType(Resources::XML_CONTENT_TYPE);
        $entry->setContent($content);
        $entry->setAttribute(
            Resources::XMLNS_ATOM, 
            Resources::ATOM_NAMESPACE
        );

        $entry->setAttribute(
            Resources::XMLNS,
            Resources::SERVICE_BUS_NAMESPACE
        );

        $xmlWriter = new \XMLWriter();
        $xmlWriter->openMemory();
        $entry->writeXml($xmlWriter); 
        $httpCallContext->setBody($xmlWriter->outputMemory());

        $response = $this->sendContext($httpCallContext);
        $createQueueResult = new CreateQueueResult();
        $createQueueResult->parseXml($response->getBody());
        return $createQueueResult;
    } 

    /**
     * Deletes a queue. 
     * 
     * @param string $queuePath The path of the queue.
     *
     * @return none
     */
    public function deleteQueue($queuePath)
    {
        Validate::isString($queuePath, 'queuePath');
        Validate::notNullOrEmpty($queuePath, 'queuePath');
        
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_DELETE);
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $httpCallContext->setPath($queuePath);
        
        $this->sendContext($httpCallContext);
    }

    /**
     * Gets a queue with specified path. 
     * 
     * @param string $queuePath The path of the queue.
     *
     * @return none
     */
    public function getQueue($queuePath)
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setPath($queuePath);
        $httpCallContext->setMethod(Resources::HTTP_GET);
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $response = $this->sendContext($httpCallContext);
        $getQueueResult = new GetQueueResult();
        $getQueueResult->parseXml($response->getBody());
        return $getQueueResult;
    }

    /**
     * Lists a queue. 
     * 
     * @param ListQueuesOptions $listQueuesOptions The options to list the 
     * queues.
     *
     * @return ListQueuesResult;
     */
    public function listQueues($listQueuesOptions = null)
    {
        $response = $this->listOptions($listQueuesOptions, Resources::LIST_QUEUES_PATH);
        $listQueuesResult = new ListQueuesResult();
        $listQueuesResult->parseXml($response->getBody());
        return $listQueuesResult;
    }

    private function listOptions($listOptions, $path)
    {
        if (is_null($listOptions))
        {
            $listOptions = new ListOptions();
        }

        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_GET);
        $httpCallContext->setPath($path);
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $top = $listOptions->getTop();
        $skip = $listOptions->getSkip();

        if (!empty($top)) {
            $httpCallContext->addQueryParameter(Resources::QP_TOP, $top);
        } 

        if (!empty($skip)) { 
            $httpCallContext->addQueryParameter(Resources::QP_SKIP, $skip);
        }

        return $this->sendContext($httpCallContext);
    }

    /**
     * Creates a topic with specified topic info.  
     * 
     * @param TopicInfo $topicInfo The information of the topic. 
     *
     * @return CreateTopicResult 
     */
    public function createTopic($topicInfo)
    {
        Validate::notNullOrEmpty($topicInfo, 'topicInfo');
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_PUT);
        $httpCallContext->setPath($topicInfo->getTitle());
        $httpCallContext->addHeader(
            Resources::CONTENT_TYPE,
            Resources::ATOM_ENTRY_CONTENT_TYPE
        );
        $httpCallContext->addStatusCode(Resources::STATUS_CREATED);

        $topicDescriptionXml = XmlSerializer::objectSerialize(
            $topicInfo->getTopicDescription(),
            'TopicDescription'
        );

        $entry = new Entry();
        $content = new Content($topicDescriptionXml);
        $content->setType(Resources::XML_CONTENT_TYPE);
        $entry->setContent($content); 
        $entry->setAttribute(
            Resources::XMLNS_ATOM, 
            Resources::ATOM_NAMESPACE
        );

        $entry->setAttribute(
            Resources::XMLNS,
            Resources::SERVICE_BUS_NAMESPACE
        );

        $xmlWriter = new \XMLWriter();
        $xmlWriter->openMemory();
        $entry->writeXml($xmlWriter); 
        $httpCallContext->setBody($xmlWriter->outputMemory());

        $response = $this->sendContext($httpCallContext);
        $createTopicResult = new CreateTopicResult();
        $createTopicResult->parseXml($response->getBody());
        return $createTopicResult;
    } 

    /**
     * Deletes a topic with specified topic path. 
     * 
     * @param string $topicPath The path of the topic.
     *
     * @return none
     */
    public function deleteTopic($topicPath)
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_DELETE);
        $httpCallContext->setPath($topicPath);     
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        
        $this->sendContext($httpCallContext);
    }
    
    /**
     * Gets a topic. 
     * 
     * @param string $topicPath The path of the topic.
     *
     * @return GetTopicResult;
     */
    public function getTopic($topicPath) 
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_GET);
        $httpCallContext->setPath($topicPath);
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $response       = $this->sendContext($httpCallContext);
        $getTopicResult = new GetTopicResult();
        $getTopicResult->parseXml($response->getBody());
        return $getTopicResult; 
    }
    
    /**
     * Lists topics. 
     * 
     * @param ListTopicsOptions $listTopicsOptions The options to list 
     * the topics. 
     *
     * @return ListTopicsResults
     */
    public function listTopics($listTopicsOptions = null) 
    {
        $response         = $this->listOptions(
            $listTopicsOptions, 
            Resources::LIST_TOPICS_PATH
        );

        $listTopicsResult = new ListTopicsResult();
        $listTopicsResult->parseXml($response->getBody());
        return $listTopicsResult;
    }

    /**
     * Creates a subscription with specified topic path and 
     * subscription info. 
     * 
     * @param string                  $topicPath               The path of
     * the topic.
     * @param SubscriptionDescription $subscriptionDescription The description
     * of the subscription.
     *
     * @return CreateSubscriptionResult
     */
    public function createSubscription($topicPath, $subscriptionInfo) 
    {
        $httpCallContext = new HttpCallContext(); 
        $httpCallContext->setMethod(Resources::HTTP_PUT);
        $subscriptionPath = sprintf(
            Resources::SUBSCRIPTION_PATH, 
            $topicPath,
            $subscriptionInfo->getTitle()
        );
        $httpCallContext->setPath($subscriptionPath);
        $httpCallContext->addHeader(
            Resources::CONTENT_TYPE,    
            Resources::ATOM_ENTRY_CONTENT_TYPE
        );
        $httpCallContext->addStatusCode(Resources::STATUS_CREATED);

        $subscriptionDescriptionXml = XmlSerializer::objectSerialize(
            $subscriptionInfo->getSubscriptionDescription(),
            'SubscriptionDescription'
        );

        $entry = new Entry();
        $content = new Content($subscriptionDescriptionXml);
        $content->setType(Resources::XML_CONTENT_TYPE);
        $entry->setContent($content);
        $entry->setAttribute(
            Resources::XMLNS_ATOM,
            Resources::ATOM_NAMESPACE
        );

        $entry->setAttribute(
            Resources::XMLNS,
            Resources::SERVICE_BUS_NAMESPACE
        );

        $xmlWriter = new \XMLWriter();
        $xmlWriter->openMemory();
        $entry->writeXml($xmlWriter); 
        $httpCallContext->setBody($xmlWriter->outputMemory());

        $response                 = $this->sendContext($httpCallContext);
        $createSubscriptionResult = new CreateSubscriptionResult();
        $createSubscriptionResult->parseXml($response->getBody());
        return $createSubscriptionResult;
    }

    /**
     * Deletes a subscription. 
     * 
     * @param string $topicPath        The path of the topic.
     * @param string $subscriptionName The name of the subscription.
     *
     * @return none
     */
    public function deleteSubscription($topicPath, $subscriptionName) 
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_DELETE);
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $subscriptionPath = sprintf(
            Resources::SUBSCRIPTION_PATH,
            $topicPath,
            $subscriptionName
        );
        $httpCallContext->setPath($subscriptionPath);
        $this->sendContext($httpCallContext);
    }
    
    /**
     * Gets a subscription. 
     * 
     * @param string $topicPath        The path of the topic.
     * @param string $subscriptionName The name of the subscription.
     *
     * @return GetSubscriptionResult
     */
    public function getSubscription($topicPath, $subscriptionName) 
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_GET);
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $subscriptionPath = sprintf(
            Resources::SUBSCRIPTION_PATH,
            $topicPath,
            $subscriptionName
        );
        $httpCallContext->setPath($subscriptionPath);
        $response              = $this->sendContext($httpCallContext);
        $getSubscriptionResult = new GetSubscriptionResult();
        $getSubscriptionResult->parseXml($response->getBody()); 
        return $getSubscriptionResult;
    }

    /**
     * Lists subscription. 
     * 
     * @param string                   $topicPath               The path of 
     * the topic.
     * @param ListSubscriptionsOptions $listSubscriptionsOptions The options
     * to list the subscription. 
     *
     * @return ListSubscriptionsResult
     */
    public function listSubscriptions(
        $topicPath, 
        $listSubscriptionsOptions = null) 
    {
        $listSubscriptionsPath = sprintf(
            Resources::LIST_SUBSCRIPTIONS_PATH, 
            $topicPath
        );
        $response                = $this->listOptions($listSubscriptionsOptions, $listSubscriptionsPath);
        $listSubscriptionsResult = new ListSubscriptionsResult();
        $listSubscriptionsResult->parseXml($response->getBody());
        return $listSubscriptionsResult; 
    }

    /**
     * Creates a rule. 
     * 
     * @param string   $topicPath        The path of the topic.
     * @param string   $subscriptionName The name of the subscription. 
     * @param RuleInfo $ruleInfo         The information of the rule.
     *
     * @return CreateRuleResult
     */
    public function createRule($topicPath, $subscriptionName, $ruleInfo)
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_PUT);
        $httpCallContext->addStatusCode(Resources::STATUS_CREATED);
        $httpCallContext->addHeader(
            Resources::CONTENT_TYPE,
            Resources::ATOM_ENTRY_CONTENT_TYPE
        );
        $rulePath = sprintf(
            Resources::RULE_PATH,
            $topicPath,
            $subscriptionName,
            $ruleInfo->getTitle()
        );

        $ruleDescriptionXml = XmlSerializer::objectSerialize(
            $ruleInfo->getRuleDescription(),
            'RuleDescription'
        );

        $entry   = new Entry();
        $content = new Content($ruleDescriptionXml);
        $content->setType(Resources::XML_CONTENT_TYPE);
        $entry->setContent($content);
        $entry->setAttribute(
            Resources::XMLNS_ATOM,
            Resources::ATOM_NAMESPACE
        );

        $entry->setAttribute(
            Resources::XMLNS,
            Resources::SERVICE_BUS_NAMESPACE
        );

        $xmlWriter = new \XMLWriter();
        $xmlWriter->openMemory();
        $entry->writeXml($xmlWriter); 
        $httpCallContext->setBody($xmlWriter->outputMemory());

        $httpCallContext->setPath($rulePath);
        $response         = $this->sendContext($httpCallContext);
        $createRuleResult = new CreateRuleResult();
        $createRuleResult->parseXml($response->getBody()); 
        return $createRuleResult;
    }

    /**
     * Deletes a rule. 
     * 
     * @param string $topicPath        The path of the topic.
     * @param string $subscriptionName The name of the subscription.
     * @param string $ruleName         The name of the rule.
     *
     * @return none
     */
    public function deleteRule($topicPath, $subscriptionName, $ruleName) 
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $httpCallContext->setMethod(Resources::HTTP_DELETE);
        $rulePath = sprintf(
            Resources::RULE_PATH,
            $topicPath,
            $subscriptionName,
            $ruleName
        );
        $httpCallContext->setPath($rulePath);
        $this->sendContext($httpCallContext);
    }

    /**
     * Gets a rule. 
     * 
     * @param string $topicPath        The path of the topic.
     * @param string $subscriptionName The name of the subscription.
     * @param string $ruleName         The name of the rule.
     *
     * @return GetRuleResult
     */
    public function getRule($topicPath, $subscriptionName, $ruleName) 
    {
        $httpCallContext = new HttpCallContext();
        $httpCallContext->setMethod(Resources::HTTP_GET);
        $httpCallContext->addStatusCode(Resources::STATUS_OK);
        $rulePath = sprintf(
            Resources::RULE_PATH,
            $topicPath,
            $subscriptionName,
            $ruleName
        );
        $httpCallContext->setPath($rulePath);
        $response      = $this->sendContext($httpCallContext);
        $getRuleResult = new GetRuleResult();
        $getRuleResult->parseXml($response->getBody());
        return $getRuleResult;
    }

    /**
     * Lists rules. 
     * 
     * @param string           $topicPath        The path of the topic.
     * @param string           $subscriptionName The name of the subscription.
     * @param ListRulesOptions $listRulesOptions The options to list the rules.
     *
     * @return ListRuleResult
     */
    public function listRules(
        $topicPath, 
        $subscriptionName, 
        $listRulesOptions = null
    ) {
        $listRulesPath = sprintf(
            Resources::LIST_RULES_PATH,
            $topicPath,
            $subscriptionName
        );

        $response        = $this->listOptions(
            $listRulesOptions, 
            $listRulesPath
        );

        $listRulesResult = new ListRulesResult();
        $listRulesResult->parseXml($response->getBody());
        return $listRulesResult;
    }
    
}