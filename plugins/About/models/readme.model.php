<?php

class testModel extends PHPDS_model
{
    public function helloWorld()
    {
        return $this->configuration['user_display_name'];
    }
}

return 'testModel';