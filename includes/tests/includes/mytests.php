<?php

require_once "myutils.php";

class UtilsTest extends PHPUnit_Framework_TestCase {

    public function testUtils() {
        // Tests the add function
        $expected = 20;
        $actual = add(15, 5);
        $this->assertEquals($expected, $actual);

        // Tests the subtract function
        $expected = 10;
        $actual = subtract(15, 5);
        $this->assertEquals($expected, $actual);

        // Tests the multiply function
        $expected = 25;
        $actual = multiply(5, 5);
        $this->assertEquals($expected, $actual);

        // Tests the divide function
        $expected = 10;
        $actual = divide(20, 2);
        $this->assertEquals($expected, $actual);
    }
}
