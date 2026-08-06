<?php
declare(strict_types=1);
use yii\db\Migration;
class m260806_140806_add_pending_processing_status_to_client_table extends Migration
{
    public function safeUp(): void
    {
        $this->addColumn(
            '{{%client}}',
            'pending_processing_status',
            $this->string(16)
                ->notNull()
                ->defaultValue('idle')
                ->after('status')
        );
    }

    public function safeDown(): void
    {
        $this->dropColumn('{{%client}}', 'pending_processing_status');
    }
}
