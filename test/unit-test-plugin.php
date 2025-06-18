<?php

class PluginTest extends TestCase
{
    public function test_plugin_installed() {
        activate_plugin( 'dt-webform/dt-webform.php' );

        $this->assertContains(
            'dt-webform/dt-webform.php',
            get_option( 'active_plugins' )
        );
    }
}
