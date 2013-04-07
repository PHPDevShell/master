<?

$testing_dir = "/var/www/websentinel/";

require_once $testing_dir."tests/config/config.php";
require_once $testing_dir."lib/core.php";
require_once $testing_dir."lib/dbi.php";
require_once $testing_dir."lib/dbpdo.php";

require_once "/var/www/dummy/dummy/dummy.php";

class DBPDOTest extends PHPUnit_Framework_TestCase {
    private $db = false;

    protected function setUp() {
        global $config;

        // Database Connection
        $this->db = new DBPDO();
        $this->db->dbDSN = $config['db_dsn'];
        $this->db->dbUsername = $config['db_username'];
        $this->db->dbPassword = $config['db_password'];
        $this->db->dbPersistent = $config['db_persistent'];
        $this->db->connect();

        // Start transaction
        $this->db->startTransaction();

        // Drop existing test tables
        $this->db->query("DROP TABLE IF EXISTS dwf_client_users");
        $this->db->query("DROP TABLE IF EXISTS dwf_users");
        $this->db->query("DROP TABLE IF EXISTS dwf_clients");

        // Recreate the test tables
        $this->db->query("
            CREATE TABLE IF NOT EXISTS `dwf_clients` (
              `client_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `name` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
              `cell_no` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
              `work_no` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
              `home_no` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
              `email` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
              `ref_no` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
              `additional_info` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
              `balance` float NOT NULL,
              `inv_addr_1` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
              `inv_addr_2` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
              `inv_addr_3` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
              `inv_addr_4` varchar(250) COLLATE utf8_unicode_ci NOT NULL,
              `inv_postal_code` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
              `notes` text COLLATE utf8_unicode_ci NOT NULL,
              `notification_emails` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
              `notification_sms` varchar(1024) COLLATE utf8_unicode_ci NOT NULL,
              `ntfy_email_count` int(10) unsigned NOT NULL,
              `ntfy_sms_count` int(10) unsigned NOT NULL,
              PRIMARY KEY (`client_id`),
              KEY `name` (`name`),
              KEY `ref_no` (`ref_no`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `dwf_users` (
              `user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `client_user_id` int(10) unsigned NOT NULL,
              `username` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
              `password` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
              `email` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
              `display_name` varchar(120) COLLATE utf8_unicode_ci NOT NULL,
              `cell_no` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
              PRIMARY KEY (`user_id`),
              KEY `client_id` (`client_user_id`),
              KEY `username` (`username`),
              KEY `email` (`email`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ");

        $this->db->query("
            CREATE TABLE IF NOT EXISTS `dwf_client_users` (
              `client_user_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
              `client_id` int(10) unsigned NOT NULL,
              `user_id` int(10) unsigned NOT NULL,
              PRIMARY KEY (`client_user_id`),
              KEY `client_id` (`client_id`),
              KEY `user_id` (`user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;
        ");

        $this->db->commit();

        $this->db->queryCount = 0;
    }

    protected function tearDown() {
    }

    /**
     * @group db
     */
    public function testDBPDO() {
        // Add a record to our tables
        $this->db->startTransaction();

        $username = $this->db->escape("testuser");
        $email = $this->db->escape("testuser@somesite.com");
        $display_name = $this->db->escape("Test' User");
        $this->assertEquals($display_name, "Test\' User");

        // Empty query and invalid fetch
        $result = $this->db->query("");
        $this->assertFalse($result);
        $this->assertFalse($this->db->result);
        $this->assertFalse($this->db->fetch());

        // Invalid execute statement
        $result = $this->db->execute(null);
        $this->assertFalse($result);
        $this->assertFalse($this->db->result);

        // Test Insert
        $result = $this->db->query("INSERT INTO dwf_users (username, email, display_name) VALUES ('{$username}', '{$email}', '{$display_name}')");
        $this->assertEquals(get_class($result), 'PDOStatement');
        $this->assertEquals(get_class($this->db->result), 'PDOStatement');
        $this->assertEquals($this->db->lastId(), 1);

        // Test Update
        $username = $this->db->escape("newtestuser");
        $result = $this->db->query("UPDATE dwf_users SET username = '{$username}' WHERE user_id = 1");
        $this->assertEquals(get_class($result), 'PDOStatement');
        $this->assertEquals(get_class($this->db->result), 'PDOStatement');
        $this->assertEquals($this->db->affectedRows(), 1);

        // Test Select
        $result = $this->db->query("SELECT * FROM dwf_users WHERE user_id = 1");
        $this->assertEquals(get_class($result), 'PDOStatement');
        $this->assertEquals(get_class($this->db->result), 'PDOStatement');

        // Test Select Result
        $this->assertEquals($this->db->numRows(), 1);
        $result = $this->db->fetch();
        $this->assertEquals($result['user_id'], 1);
        $this->assertEquals($result['client_user_id'], 0);
        $this->assertEquals($result['username'], "newtestuser");
        $this->assertEquals($result['password'], "");
        $this->assertEquals($result['email'], "testuser@somesite.com");
        $this->assertEquals($result['display_name'], "Test' User");
        $this->assertEquals($result['cell_no'], "");

        // Test Prepared Insert Statement
        $sql = "INSERT INTO dwf_users (username, email, display_name) VALUES (:username, :email, :display_name)";
        $this->db->prepare($sql);
        $result = $this->db->execute(array('username' => "anotheruser", 'email' => "anotheruser@coolsite.org", 'display_name' => "Rick's Rolled"));
        $this->assertEquals(get_class($result), 'PDOStatement');
        $this->assertEquals(get_class($this->db->result), 'PDOStatement');
        $this->assertEquals($this->db->lastId(), 2);

        //$this->assertEquals($this->db->queryCount, 1);

        // Test Un-Prepared Insert Statement
        $sql = "INSERT INTO dwf_users (client_user_id, username, email, display_name) VALUES (:client_user_id, :username, :email, :display_name)";
        $result = $this->db->query($sql, array('client_user_id' => 1, 'username' => "thirduser", 'email' => "thirduser@gaagle.com", 'display_name' => "Third Guy"));
        $this->assertEquals(get_class($result), 'PDOStatement');
        $this->assertEquals(get_class($this->db->result), 'PDOStatement');
        $this->assertEquals($this->db->lastId(), 3);

        // Test Prepared Select Statement
        $sql = "SELECT * FROM dwf_users WHERE username = :username";
        $this->db->prepare($sql);
        $result = $this->db->execute(array('username' => "anotheruser"));

        $this->assertEquals(get_class($result), 'PDOStatement');
        $this->assertEquals(get_class($this->db->result), 'PDOStatement');

        $this->assertEquals($this->db->numRows(), 1);


        // Test Prepared Select Result
        $this->db->execute(array('username' => "anotheruser"));
        $result = $this->db->fetch();
        $this->assertEquals($result['user_id'], 2);
        $this->assertEquals($result['client_user_id'], 0);
        $this->assertEquals($result['username'], "anotheruser");
        $this->assertEquals($result['password'], "");
        $this->assertEquals($result['email'], "anotheruser@coolsite.org");
        $this->assertEquals($result['display_name'], "Rick's Rolled");
        $this->assertEquals($result['cell_no'], "");

        // Test Prepared Select Result (Defaults to MODE_ASSOC)
        $this->db->execute(array('username' => "anotheruser"));
        $this->assertEquals($this->db->numRows(), 1);
        $result = $this->db->fetch(-1);
        $this->assertEquals($result['user_id'], 2);
        $this->assertEquals($result['client_user_id'], 0);
        $this->assertEquals($result['username'], "anotheruser");
        $this->assertEquals($result['password'], "");
        $this->assertEquals($result['email'], "anotheruser@coolsite.org");
        $this->assertEquals($result['display_name'], "Rick's Rolled");
        $this->assertEquals($result['cell_no'], "");

        // Test Fetch Mode Assoc
        $this->db->execute(array('username' => "anotheruser"));
        $result = $this->db->fetch(DBPDO::MODE_ASSOC);
        $this->assertEquals($result['user_id'], 2);
        $this->assertEquals($result['client_user_id'], 0);
        $this->assertEquals($result['username'], "anotheruser");
        $this->assertEquals($result['password'], "");
        $this->assertEquals($result['email'], "anotheruser@coolsite.org");
        $this->assertEquals($result['display_name'], "Rick's Rolled");
        $this->assertEquals($result['cell_no'], "");

        // Test Fetch Assoc
        $this->db->execute(array('username' => "anotheruser"));
        $result = $this->db->fetchAssoc();
        $this->assertEquals($result['user_id'], 2);
        $this->assertEquals($result['client_user_id'], 0);
        $this->assertEquals($result['username'], "anotheruser");
        $this->assertEquals($result['password'], "");
        $this->assertEquals($result['email'], "anotheruser@coolsite.org");
        $this->assertEquals($result['display_name'], "Rick's Rolled");
        $this->assertEquals($result['cell_no'], "");

        // Test Fetch Mode Num
        $this->db->execute(array('username' => "anotheruser"));
        $result = $this->db->fetch(DBPDO::MODE_NUM);
        $this->assertEquals($result[0], 2);
        $this->assertEquals($result[1], 0);
        $this->assertEquals($result[2], "anotheruser");
        $this->assertEquals($result[3], "");
        $this->assertEquals($result[4], "anotheruser@coolsite.org");
        $this->assertEquals($result[5], "Rick's Rolled");
        $this->assertEquals($result[6], "");

        // Test Fetch Mode Both
        $this->db->execute(array('username' => "anotheruser"));
        $result = $this->db->fetch(DBPDO::MODE_BOTH);
        $this->assertEquals($result[0], 2);
        $this->assertEquals($result[1], 0);
        $this->assertEquals($result[2], "anotheruser");
        $this->assertEquals($result[3], "");
        $this->assertEquals($result[4], "anotheruser@coolsite.org");
        $this->assertEquals($result[5], "Rick's Rolled");
        $this->assertEquals($result[6], "");

        $this->assertEquals($result['user_id'], 2);
        $this->assertEquals($result['client_user_id'], 0);
        $this->assertEquals($result['username'], "anotheruser");
        $this->assertEquals($result['password'], "");
        $this->assertEquals($result['email'], "anotheruser@coolsite.org");
        $this->assertEquals($result['display_name'], "Rick's Rolled");
        $this->assertEquals($result['cell_no'], "");

        // Test Fetch Object Mode
        $this->db->execute(array('username' => "anotheruser"));
        $result = $this->db->fetch(DBPDO::MODE_OBJECT);
        $this->assertEquals($result->user_id, 2);
        $this->assertEquals($result->client_user_id, 0);
        $this->assertEquals($result->username, "anotheruser");
        $this->assertEquals($result->password, "");
        $this->assertEquals($result->email, "anotheruser@coolsite.org");
        $this->assertEquals($result->display_name, "Rick's Rolled");
        $this->assertEquals($result->cell_no, "");

        // Test Fetch Object
        $this->db->execute(array('username' => "anotheruser"));
        $result = $this->db->fetchObject();
        $this->assertEquals($result->user_id, 2);
        $this->assertEquals($result->client_user_id, 0);
        $this->assertEquals($result->username, "anotheruser");
        $this->assertEquals($result->password, "");
        $this->assertEquals($result->email, "anotheruser@coolsite.org");
        $this->assertEquals($result->display_name, "Rick's Rolled");
        $this->assertEquals($result->cell_no, "");

        $this->db->commit();

        // Perform rollback test
        $this->db->startTransaction();
        $this->db->rollBack();

    }
}