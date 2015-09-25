<?php

namespace spec\Betacie\MailchimpBundle\Subscriber;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Mailchimp;
use Mailchimp_Lists;
use Psr\Log\LoggerInterface;
use Betacie\MailchimpBundle\Subscriber\Subscriber;

class ListRepositorySpec extends ObjectBehavior
{
    function let(Mailchimp $mailchimp, LoggerInterface $logger, Mailchimp_Lists $mailchimpLists, Subscriber $subscriber)
    {
        $this->prepareSubscriber($subscriber);
        $this->prepareMailchimpLists($mailchimpLists);

        $mailchimp->lists = $mailchimpLists;

        $this->beConstructedWith($mailchimp, $logger);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Betacie\MailchimpBundle\Subscriber\ListRepository');
    }

    function it_can_find_a_list_by_its_name($mailchimpLists)
    {
        $this->findByName('toto')->shouldReturn(['id' => 123]);
        $this
            ->shouldThrow(new \RuntimeException('The list "tutu" was not found in Mailchimp. You need to create it first in Mailchimp backend.'))
            ->duringFindByName('tutu')
        ;
    }

    function it_subscribe_a_subscriber($subscriber, $mailchimpLists)
    {
        $mailchimpLists->subscribe(
            1337,
            ['email' => 'charles@terrasse.fr'],
            ['FIRSTNAME' => 'Charles'],
            'html',
            false,
            true
        )->shouldBeCalled();

        $this->subscribe(1337, $subscriber);
    }

    function it_subscribes_several_subscribers($mailchimpLists, $logger)
    {
        $firstChunk = $this->getSubscriberChunk(500, 0);
        $secondChunk = $this->getSubscriberChunk(500, 500);
        $thirdChunk = $this->getSubscriberChunk(123, 1000);

        $subscribers = array_merge($firstChunk, $secondChunk, $thirdChunk);

        $options = ['mc_language' => 'fr'];

        $mailchimpLists->batchSubscribe(
            42,
            $this->formatMailChimp($firstChunk, $options),
            false,
            true
        )->willReturn([
            'add_count' => 500,
            'update_count' => 0,
            'error_count' => 0,
            'success_count' => 0,
            'errors' => []
        ]);
        
        $mailchimpLists->batchSubscribe(
            42,
            $this->formatMailChimp($secondChunk, $options),
            false,
            true
        )->willReturn([
            'add_count' => 250,
            'update_count' => 250,
            'error_count' => 0,
            'success_count' => 0,
            'errors' => []
        ]);

        $mailchimpLists->batchSubscribe(
            42,
            $this->formatMailChimp($thirdChunk, $options),
            false,
            true
        )->willReturn([
            'add_count' => 100,
            'update_count' => 22,
            'error_count' => 1,
            'success_count' => 0,
            'errors' => [
                ['email' => ['email' => 'foo@bar.com'], 'error' => 'Foo has errored.']
            ]
        ]);

        $logger->info('850 subscribers added.')->shouldBeCalled();
        $logger->info('272 subscribers updated.')->shouldBeCalled();
        $logger->error('1 subscribers errored.')->shouldBeCalled();
        $logger->error('Subscriber "foo@bar.com" has not been processed: "Foo has errored."')->shouldBeCalled();

        $this->batchSubscribe(42, $subscribers, $options);
    }

    function it_unsubscribes_several_subscribers($mailchimpLists, $logger)
    {
        $firstChunk = $this->getSubscriberChunk(500, 0);
        $secondChunk = $this->getSubscriberChunk(500, 500);
        $thirdChunk = $this->getSubscriberChunk(123, 1000);

        $subscribers = array_merge($firstChunk, $secondChunk, $thirdChunk);

        $options = ['mc_language' => 'fr'];

        $mailchimpLists->batchSubscribe(
            42,
            $this->formatMailChimp($firstChunk, $options),
            false,
            true
        )->willReturn([
            'add_count' => 500,
            'update_count' => 0,
            'error_count' => 0,
            'success_count' => 0,
            'errors' => []
        ]);
        
        $mailchimpLists->batchSubscribe(
            42,
            $this->formatMailChimp($secondChunk, $options),
            false,
            true
        )->willReturn([
            'add_count' => 250,
            'update_count' => 250,
            'error_count' => 0,
            'success_count' => 0,
            'errors' => []
        ]);

        $mailchimpLists->batchSubscribe(
            42,
            $this->formatMailChimp($thirdChunk, $options),
            false,
            true
        )->willReturn([
            'add_count' => 100,
            'update_count' => 22,
            'error_count' => 1,
            'success_count' => 0,
            'errors' => [
                ['email' => ['email' => 'foo@bar.com'], 'error' => 'Foo has errored.']
            ]
        ]);

        $logger->info('850 subscribers added.')->shouldBeCalled();
        $logger->info('272 subscribers updated.')->shouldBeCalled();
        $logger->error('1 subscribers errored.')->shouldBeCalled();
        $logger->error('Subscriber "foo@bar.com" has not been processed: "Foo has errored."')->shouldBeCalled();

        $this->batchSubscribe(42, $subscribers, $options);
    }

    function it_unsubscribe_a_subscriber($subscriber, $mailchimpLists)
    {
        $mailchimpLists->unsubscribe(
            1337,
            ['email' => 'charles@terrasse.fr'],
            true,
            false,
            false
        )->shouldBeCalled();

        $this->unsubscribe(1337, $subscriber);
    }

    function it_finds_merge_tags($mailchimpLists)
    {
        $mailchimpLists
            ->mergeVars([123])
            ->willReturn([
                'data' => [
                    [
                        'id' => 123, 
                        'merge_vars' => [
                            ['tag' => 'EMAIL'], //this tag should be ignored
                            ['tag' => 'FOO', 'name' => 'Bar'],
                        ]
                    ]
                ]
            ])
        ;

        $this->findMergeTags(123)->shouldReturn([['tag' => 'FOO', 'name' => 'Bar']]);
    }

    function it_deletes_a_merge_tag($mailchimpLists, $logger)
    {
        $mailchimpLists->mergeVarDel(123, 'FOO')->shouldBeCalled();

        $this->deleteMergeTag(123, 'FOO');

        $logger->info('Tag "FOO" has been removed from MailChimp.');
    }

    function it_adds_a_merge_tag($mailchimpLists, $logger)
    {
        $mailchimpLists->mergeVarAdd(123, 'FOO', 'Foo bar', ['req' => true])->shouldBeCalled();

        $this->addMergeTag(123, [
            'tag' => 'FOO',
            'name' => 'Foo bar',
            'options' => ['req' => true]
        ]);

        $logger->info('Tag "FOO" has been added to MailChimp.');
    }

    function it_updates_a_merge_tag($mailchimpLists, $logger)
    {
        $mailchimpLists->mergeVarUpdate(123, 'FOO', ['name' => 'Foo bar', 'req' => true])->shouldBeCalled();

        $this->updateMergeTag(123, [
            'tag' => 'FOO',
            'name' => 'Foo bar',
            'options' => [
                'req' => true, 
                'field_type' => 'text' // should be removed, cannot be updated
            ]
        ]);

        $logger->info('Tag "FOO" has been updated in MailChimp.');
    }

    protected function prepareSubscriber(Subscriber $subscriber)
    {
        $subscriber->getEmail()->willReturn('charles@terrasse.fr');
        $subscriber->getMergeTags()->willReturn(['FIRSTNAME' => 'Charles']);
    }

    protected function prepareMailchimpLists(Mailchimp_Lists $mailchimpLists)
    {
        $mailchimpLists->getList(['list_name' => 'toto'])->willReturn([
            'total' => 1, 
            'data' => [
                ['id' => 123]
            ]
        ]);

        $mailchimpLists->getList(['list_name' => 'tutu'])->willReturn([
            'total' => 0
        ]);
    }

    protected function getSubscriberChunk($count, $offset)
    {
        $subscribers = [];
        for ($i = $offset; $i < $offset + $count; $i++) {
            $subscribers[] = new Subscriber(sprintf('email%s@example.org', $i));
        }

        return $subscribers;
    }

    protected function formatMailChimp(array $subscribers, array $options = [])
    {
        return array_map(function(Subscriber $subscriber) use ($options) {
            return [
                'email' => ['email' => $subscriber->getEmail()],
                'merge_vars' => array_merge($options, $subscriber->getMergeTags())
            ];
        }, $subscribers);
    }
}
