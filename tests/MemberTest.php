<?php
/**
 * Created by PhpStorm.
 * User: kimura
 * Date: 2016/11/27
 * Time: 13:52
 */

require_once dirname(__DIR__).DIRECTORY_SEPARATOR.'rltam.php';

class MemberTest extends PHPUnit_Framework_TestCase
{
    /*
    public function setUp()
    {
        // parent::setUp(); // TODO: Change the autogenerated stub
        $this->member = new Member();
    }
    
    public function tearDown()
    {
        // parent::tearDown(); // TODO: Change the autogenerated stub
        $this->member = null;
    }
    */
    
    public function testMain()
    {
    
        $this->member = new Member();
        
        $this->assertFalse($this->member->isEnabled());
        
        $this->member->setName('tecokimura');
        $this->assertTrue($this->member->isEnabled());
        $this->member->setMail('tecokimura@gmail.com');
        $this->assertTrue($this->member->isEnabled());
        
        $this->assertFalse($this->member->isDirName());
        $this->member->setDirName('aaa');
        $this->assertTrue($this->member->isDirName());
        
        $this->assertCount(0, $this->member->getAryFilePath());
        $this->member->addFilePath('path');
        $this->assertCount(1, $this->member->getAryFilePath());
        $this->member->addFilePath('path');
        $this->member->addFilePath('path');
        $this->assertCount(3, $this->member->getAryFilePath());

    }
}