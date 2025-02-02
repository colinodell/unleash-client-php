<?php

namespace Unleash\Client\Tests\Strategy;

use JetBrains\PhpStorm\ExpectedValues;
use JetBrains\PhpStorm\Pure;
use PHPUnit\Framework\TestCase;
use Unleash\Client\Configuration\UnleashContext;
use Unleash\Client\DTO\DefaultConstraint;
use Unleash\Client\DTO\DefaultStrategy;
use Unleash\Client\DTO\Strategy;
use Unleash\Client\Enum\ConstraintOperator;
use Unleash\Client\Enum\Stickiness;
use Unleash\Client\Stickiness\MurmurHashCalculator;
use Unleash\Client\Strategy\GradualRolloutStrategyHandler;

final class GradualRolloutStrategyHandlerTest extends TestCase
{
    /**
     * @var GradualRolloutStrategyHandler
     */
    private $instance;

    protected function setUp(): void
    {
        $this->instance = new GradualRolloutStrategyHandler(new MurmurHashCalculator());
    }

    public function testSupports()
    {
        self::assertFalse($this->instance->supports(new DefaultStrategy('default', [])));
        self::assertTrue($this->instance->supports(new DefaultStrategy('flexibleRollout', [])));
        self::assertFalse($this->instance->supports(new DefaultStrategy('remoteAddress', [])));
        self::assertFalse($this->instance->supports(new DefaultStrategy('userWithId', [])));
        self::assertFalse($this->instance->supports(new DefaultStrategy('nonexistent', [])));
    }

    public function testIsEnabled()
    {
        // no exception should be thrown
        $this->instance->isEnabled($this->createStrategy(), new UnleashContext());

        self::assertFalse($this->instance->isEnabled(new DefaultStrategy('flexibleRollout', [
            'groupId' => 'test',
            'rollout' => 5,
        ]), new UnleashContext()));

        self::assertFalse($this->instance->isEnabled(new DefaultStrategy('flexibleRollout', [
            'groupId' => 'test',
            'stickiness' => Stickiness::RANDOM,
        ]), new UnleashContext()));

        self::assertFalse($this->instance->isEnabled(new DefaultStrategy('flexibleRollout', [
            'groupId' => 'test',
            'stickiness' => 'unknown-stickiness',
            'rollout' => 5,
        ]), new UnleashContext()));

        self::assertFalse($this->instance->isEnabled($this->createStrategy(50), new UnleashContext('123')));
        self::assertFalse($this->instance->isEnabled($this->createStrategy(50), new UnleashContext('456')));
        self::assertTrue($this->instance->isEnabled($this->createStrategy(50), new UnleashContext('634')));

        self::assertFalse(
            $this->instance->isEnabled(
                $this->createStrategy(50),
                new UnleashContext('123', null, '634')
            )
        );
        self::assertFalse(
            $this->instance->isEnabled(
                $this->createStrategy(50),
                new UnleashContext('456', null, '634')
            )
        );
        self::assertTrue(
            $this->instance->isEnabled(
                $this->createStrategy(50),
                new UnleashContext('634', null, '123')
            )
        );

        self::assertFalse($this->instance->isEnabled(
            $this->createStrategy(100, Stickiness::USER_ID),
            new UnleashContext()
        ));

        self::assertFalse($this->instance->isEnabled(
            $this->createStrategy(100, Stickiness::USER_ID),
            new UnleashContext(null, null, 'test')
        ));

        self::assertFalse(
            $this->instance->isEnabled(
                $this->createStrategy(50, Stickiness::USER_ID),
                new UnleashContext('456')
            )
        );
        self::assertTrue(
            $this->instance->isEnabled(
                $this->createStrategy(50, Stickiness::USER_ID),
                new UnleashContext('634')
            )
        );

        self::assertFalse($this->instance->isEnabled(
            $this->createStrategy(100, Stickiness::SESSION_ID),
            new UnleashContext()
        ));

        self::assertFalse($this->instance->isEnabled(
            $this->createStrategy(100, Stickiness::SESSION_ID),
            new UnleashContext('test')
        ));

        self::assertFalse(
            $this->instance->isEnabled(
                $this->createStrategy(50, Stickiness::SESSION_ID),
                new UnleashContext(null, null, '456')
            )
        );
        self::assertTrue(
            $this->instance->isEnabled(
                $this->createStrategy(50, Stickiness::SESSION_ID),
                new UnleashContext(null, null, '634')
            )
        );

        $this->instance->isEnabled($this->createStrategy(100, Stickiness::RANDOM), new UnleashContext());

        $strategy = $this->createStrategy(100, Stickiness::DEFAULT, [
            new DefaultConstraint('something', ConstraintOperator::IN_LIST, ['test']),
        ]);
        self::assertFalse($this->instance->isEnabled($strategy, new UnleashContext()));
        self::assertTrue($this->instance->isEnabled(
            $strategy,
            (new UnleashContext())->setCustomProperty('something', 'test')
        ));

        $strategy = $this->createStrategy(100, Stickiness::DEFAULT, [
            new DefaultConstraint('something', ConstraintOperator::NOT_IN_LIST, ['test']),
        ]);
        self::assertTrue($this->instance->isEnabled($strategy, new UnleashContext()));
        self::assertFalse($this->instance->isEnabled(
            $strategy,
            (new UnleashContext())->setCustomProperty('something', 'test')
        ));
    }

    #[Pure]
    private function createStrategy(
        int $percentage = 100,
        #[ExpectedValues(valuesFromClass: Stickiness::class)]
        $stickiness = Stickiness::DEFAULT,
        array $constraints = []
    ): Strategy {
        return new DefaultStrategy('flexibleRollout', [
            'stickiness' => $stickiness,
            'groupId'=> 'default',
            'rollout' => $percentage,
        ], $constraints);
    }
}
