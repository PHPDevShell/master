<?php

class readmeView extends PHPDS_view
{
    public function plugin()
    {
        $this->viewPlugin = $this->factory('views');
    }
}

return 'readmeView';