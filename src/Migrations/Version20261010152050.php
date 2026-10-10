<?php

declare(strict_types=1);

namespace Tmonier\SyliusWithdrawalPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Types\Types;
use Doctrine\Migrations\AbstractMigration;

/**
 * Written with the Schema API (not raw SQL) so that it runs on both MySQL and PostgreSQL.
 */
final class Version20261010152050 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create withdrawal request tables (tmonier/sylius-withdrawal-plugin).';
    }

    public function up(Schema $schema): void
    {
        $request = $schema->createTable('tmonier_withdrawal_request');
        $request->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $request->addColumn('channel_id', Types::INTEGER);
        $request->addColumn('order_id', Types::INTEGER, ['notnull' => false]);
        $request->addColumn('customer_id', Types::INTEGER, ['notnull' => false]);
        $request->addColumn('reference', Types::STRING, ['length' => 32]);
        $request->addColumn('state', Types::STRING, ['length' => 32]);
        $request->addColumn('locale_code', Types::STRING, ['length' => 12]);
        $request->addColumn('customer_name', Types::STRING, ['length' => 255]);
        $request->addColumn('email', Types::STRING, ['length' => 255]);
        $request->addColumn('order_number', Types::STRING, ['length' => 255]);
        $request->addColumn('items_description', Types::TEXT, ['notnull' => false]);
        $request->addColumn('submitted_at', Types::DATETIME_IMMUTABLE);
        $request->addColumn('deadline_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $request->addColumn('acknowledged_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $request->addColumn('processed_at', Types::DATETIME_IMMUTABLE, ['notnull' => false]);
        $request->setPrimaryKey(['id']);
        $request->addUniqueIndex(['reference'], 'UNIQ_EF7C5A99AEA34913');
        $request->addIndex(['channel_id'], 'IDX_EF7C5A9972F5A1AA');
        $request->addIndex(['order_id'], 'IDX_EF7C5A998D9F6D38');
        $request->addIndex(['customer_id'], 'IDX_EF7C5A999395C3F3');
        $request->addIndex(['state'], 'IDX_EF7C5A99A393D2FB');
        $request->addIndex(['submitted_at'], 'IDX_EF7C5A993182C73C');
        $request->addForeignKeyConstraint('sylius_channel', ['channel_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_EF7C5A9972F5A1AA');
        $request->addForeignKeyConstraint('sylius_order', ['order_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_EF7C5A998D9F6D38');
        $request->addForeignKeyConstraint('sylius_customer', ['customer_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_EF7C5A999395C3F3');

        $item = $schema->createTable('tmonier_withdrawal_request_item');
        $item->addColumn('id', Types::INTEGER, ['autoincrement' => true]);
        $item->addColumn('withdrawal_request_id', Types::INTEGER);
        $item->addColumn('order_item_id', Types::INTEGER, ['notnull' => false]);
        $item->addColumn('label', Types::STRING, ['length' => 255]);
        $item->addColumn('quantity', Types::INTEGER);
        $item->setPrimaryKey(['id']);
        $item->addIndex(['withdrawal_request_id'], 'IDX_BE9581B52E695421');
        $item->addIndex(['order_item_id'], 'IDX_BE9581B5E415FB15');
        $item->addForeignKeyConstraint('tmonier_withdrawal_request', ['withdrawal_request_id'], ['id'], ['onDelete' => 'CASCADE'], 'FK_BE9581B52E695421');
        $item->addForeignKeyConstraint('sylius_order_item', ['order_item_id'], ['id'], ['onDelete' => 'SET NULL'], 'FK_BE9581B5E415FB15');
    }

    public function down(Schema $schema): void
    {
        $schema->dropTable('tmonier_withdrawal_request_item');
        $schema->dropTable('tmonier_withdrawal_request');
    }
}
