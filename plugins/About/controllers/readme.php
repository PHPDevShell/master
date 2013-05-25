<?php

/**
 * Controller Class: Simple readme to introduce PHPDevShell.
 * @author Jason Schoeman
 * @return string
 */
class ReadMe extends PHPDS_controller
{
    /**
     * Execute Controller
     * @author Jason Schoeman
     */
    public function execute()
    {
        $this->template->heading(__('Starting with PHPDevShell'));

        $this->model->test();

        // Testing Notification Boxes.
        $warning = $this->template->warning('This is a sample warning message, this can be written in log.', 'return', 'nolog');
        $note    = $this->template->note('This is a sample notice message... ', 'return');
        $ok      = $this->template->ok('This is a sample ok message, this can be written in log.', 'return', 'nolog');
        $info    = $this->template->info('This is a sample info message...', 'return');

        $this->view->set('self_url', $this->navigation->selfUrl());
        $this->view->set('aurl', $this->configuration['absolute_url']);
        $this->view->set('note', $note);
        $this->view->set('warning', $warning);
        $this->view->set('ok', $ok);
        $this->view->set('info', $info);
        $this->view->set('script_name', $this->configuration['phpdevshell_version']);

        $this->view->show();
    }
}

return 'ReadMe';
