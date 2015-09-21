<?php

namespace spec\Betacie\MailchimpBundle\Subscriber;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument;
use Betacie\MailchimpBundle\Provider\ProviderInterface;

class SubscriberListSpec extends ObjectBehavior
{
    function let(ProviderInterface $provider)
    {
        $this->beConstructedWith('foobar', $provider);
    }

    function it_is_initializable()
    {
        $this->shouldHaveType('Betacie\MailchimpBundle\Subscriber\SubscriberList');
    }

    function it_has_a_name()
    {
        $this->getName()->shouldReturn('foobar');
    }

    function it_has_a_provider($provider)
    {
        $this->getProvider()->shouldReturn($provider);
    }

    function it_has_default_options()
    {
        $this->getOptions()->shouldReturn(['mc_language' => null]);
    }

    function it_can_set_options($provider)
    {
        $this->beConstructedWith('foobar', $provider, ['mc_language' => 'fr']);
        $this->getOptions()->shouldReturn(['mc_language' => 'fr']);
    }

    function it_cannot_set_any_option($provider)
    {
        $this
            ->shouldThrow(new \Exception('The option "bar" does not exist. Defined options are: "mc_language".'))
            ->during('__construct', ['foobar', $provider, ['bar' => 'foo']])
        ;
    }
}
