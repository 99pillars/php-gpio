<?php

namespace PhpGpio\Sensors;

use PhpGpio\Gpio;
use PhpGpio\GpioInterface;

/**
 * The MCP300X has a 10-bit analog to digital converter
 * (ADC) with a simple to use SPI interface.
 */
class Mcp300X implements SensorInterface
{
    /**
     * @var integer
     */
    protected $clockPin;

    /**
     * @var integer
     */
    protected $mosiPin;

    /**
     * @var integer
     */
    protected $misoPin;

    /**
     * @var integer
     */
    protected $csPin;

    /**
     * @var Gpio
     */
    protected $gpio;

    /**
     * Define channels count to match your MCP version :
     * - 2 channels for MCP3002
     * - 8 channels for MCP3008
     * and so on...
     *
     * @param integer $clockpin      The clock (CLK) pin (ex. 11)
     * @param integer $mosipin       The Master Out Slave In (MOSI) pin (ex. 10)
     * @param integer $misopin       The Master In Slave Out (MISO) pin (ex. 9)
     * @param integer $cspin         The Chip Select (CSna) pin (ex. 8)
     * @param integer $channelsCount Channels available
     * @param Gpio    $gpio          Gpio instance
     */
    public function __construct($clockpin, $mosipin, $misopin, $cspin, $channelsCount = 2, Gpio $gpio = null)
    {
        if (is_null($gpio)) {
            $this->gpio = new GPIO();
        }

        $this->clockPin = $clockpin;
        $this->mosiPin = $mosipin;
        $this->misoPin = $misopin;
        $this->csPin = $cspin;

        $this->gpio->setup($this->mosiPin, GpioInterface::DIRECTION_OUT);
        $this->gpio->setup($this->misoPin, GpioInterface::DIRECTION_IN);
        $this->gpio->setup($this->clockPin, GpioInterface::DIRECTION_OUT);
        $this->gpio->setup($this->csPin, GpioInterface::DIRECTION_OUT);
    }

    /**
     * Read the specified channel.
     * You should specify the channel (0|1) to read with the <tt>channel</tt> argument.
     *
     * @param array $args ['channel' => (integer)channelId]
     *
     * @return integer
     */
    public function read($args = [])
    {
        $channel = $args['channel'];
        if (!is_integer($channel) || !in_array($channel, [0, 1])) {
            echo $msg = "Only 2 channels are available on a Mcp3002: 0 or 1";
            throw new \InvalidArgumentException($msg);
        }

        // init comm
        $this->gpio->output($this->csPin, 1);
        $this->gpio->output($this->clockPin, 0);
        $this->gpio->output($this->csPin, 0);

        // channel selection
        $cmdout = (6 + $channel) << 5;
        for ($i = 0; $i < 3; $i++) {
            if ($cmdout & 0x80) {
                $this->gpio->output($this->mosiPin, 1);
            } else {
                $this->gpio->output($this->mosiPin, 0);
            }
            $cmdout <<= 1;
            $this->gpio->output($this->clockPin, 1);
            $this->gpio->output($this->clockPin, 0);
        }

        $adcout = 0;
        //  read in one empty bit, one null bit and 10 ADC bits
        for ($i = 0; $i < 12; $i++) {
            $this->gpio->output($this->clockPin, 1);
            $this->gpio->output($this->clockPin, 0);
            $adcout <<= 1;
            if ($this->gpio->input($this->misoPin)) {
                $adcout |= 0x1;
            }
        }

        $this->gpio->output($this->csPin, 1);

        return $adcout >> 1;
    }

    /**
     * {@inheritdoc}
     */
    public function write($args = [])
    {
        throw new \RuntimeException('MCP components are not writable');
    }
}