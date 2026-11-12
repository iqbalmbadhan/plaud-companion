"""Initial schema for users, workspaces, transcripts, segments, exports, and audits.

Revision ID: 001_initial_schema
Revises: 
Create Date: 2026-11-13 00:00:00.000000

"""
from typing import Sequence, Union
from alembic import op
import sqlalchemy as sa

revision: str = '001_initial_schema'
down_revision: Union[str, None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None

def upgrade() -> None:
    # Users table
    op.create_table(
        'users',
        sa.Column('id', sa.String(36), primary_key=True),
        sa.Column('email', sa.String(255), nullable=False, unique=True),
        sa.Column('display_name', sa.String(100), nullable=False),
        sa.Column('password_hash', sa.String(255), nullable=False),
        sa.Column('status', sa.String(50), nullable=False, server_default='active'),
        sa.Column('email_verified_at', sa.DateTime(timezone=True), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False),
        sa.Column('deleted_at', sa.DateTime(timezone=True), nullable=True),
    )
    op.create_index('ix_users_email', 'users', ['email'])

    # Workspaces table
    op.create_table(
        'workspaces',
        sa.Column('id', sa.String(36), primary_key=True),
        sa.Column('name', sa.String(100), nullable=False),
        sa.Column('type', sa.String(50), nullable=False, server_default='personal'),
        sa.Column('owner_user_id', sa.String(36), sa.ForeignKey('users.id', ondelete='CASCADE'), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False),
        sa.Column('deleted_at', sa.DateTime(timezone=True), nullable=True),
    )

    # Workspace Members table
    op.create_table(
        'workspace_members',
        sa.Column('id', sa.String(36), primary_key=True),
        sa.Column('workspace_id', sa.String(36), sa.ForeignKey('workspaces.id', ondelete='CASCADE'), nullable=False),
        sa.Column('user_id', sa.String(36), sa.ForeignKey('users.id', ondelete='CASCADE'), nullable=False),
        sa.Column('role', sa.String(50), nullable=False, server_default='editor'),
        sa.Column('status', sa.String(50), nullable=False, server_default='active'),
        sa.Column('joined_at', sa.DateTime(timezone=True), nullable=False),
        sa.UniqueConstraint('workspace_id', 'user_id', name='uq_workspace_member'),
    )
    op.create_index('ix_workspace_member_lookup', 'workspace_members', ['workspace_id', 'user_id'])

    # Projects table
    op.create_table(
        'projects',
        sa.Column('id', sa.String(36), primary_key=True),
        sa.Column('workspace_id', sa.String(36), sa.ForeignKey('workspaces.id', ondelete='CASCADE'), nullable=False),
        sa.Column('name', sa.String(100), nullable=False),
        sa.Column('description', sa.Text, nullable=True),
        sa.Column('status', sa.String(50), nullable=False, server_default='active'),
        sa.Column('created_by_user_id', sa.String(36), sa.ForeignKey('users.id'), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False),
        sa.Column('deleted_at', sa.DateTime(timezone=True), nullable=True),
    )

    # Transcripts table
    op.create_table(
        'transcripts',
        sa.Column('id', sa.String(36), primary_key=True),
        sa.Column('workspace_id', sa.String(36), sa.ForeignKey('workspaces.id', ondelete='CASCADE'), nullable=False),
        sa.Column('project_id', sa.String(36), sa.ForeignKey('projects.id', ondelete='SET NULL'), nullable=True),
        sa.Column('created_by_user_id', sa.String(36), sa.ForeignKey('users.id'), nullable=False),
        sa.Column('title', sa.String(255), nullable=False),
        sa.Column('description', sa.Text, nullable=True),
        sa.Column('source_type', sa.String(50), nullable=False, server_default='manual_upload'),
        sa.Column('recorded_at', sa.DateTime(timezone=True), nullable=True),
        sa.Column('language', sa.String(20), nullable=True, server_default='en'),
        sa.Column('status', sa.String(50), nullable=False, server_default='imported'),
        sa.Column('content_text', sa.Text, nullable=False),
        sa.Column('content_hash', sa.String(64), nullable=False),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False),
        sa.Column('deleted_at', sa.DateTime(timezone=True), nullable=True),
    )
    op.create_index('ix_transcripts_workspace_date', 'transcripts', ['workspace_id', 'created_at'])
    op.create_index('ix_transcripts_hash', 'transcripts', ['content_hash'])

    # Transcript Segments table
    op.create_table(
        'transcript_segments',
        sa.Column('id', sa.String(36), primary_key=True),
        sa.Column('transcript_id', sa.String(36), sa.ForeignKey('transcripts.id', ondelete='CASCADE'), nullable=False),
        sa.Column('sequence_number', sa.Integer, nullable=False),
        sa.Column('speaker_label', sa.String(100), nullable=True),
        sa.Column('start_ms', sa.Integer, nullable=True),
        sa.Column('end_ms', sa.Integer, nullable=True),
        sa.Column('text', sa.Text, nullable=False),
        sa.UniqueConstraint('transcript_id', 'sequence_number', name='uq_transcript_segment_seq'),
    )

    # Export Jobs table
    op.create_table(
        'export_jobs',
        sa.Column('id', sa.String(36), primary_key=True),
        sa.Column('workspace_id', sa.String(36), sa.ForeignKey('workspaces.id', ondelete='CASCADE'), nullable=False),
        sa.Column('requested_by_user_id', sa.String(36), sa.ForeignKey('users.id'), nullable=False),
        sa.Column('status', sa.String(50), nullable=False, server_default='queued'),
        sa.Column('template', sa.String(50), nullable=False, server_default='clean_transcript'),
        sa.Column('settings', sa.JSON, nullable=True),
        sa.Column('total_items', sa.Integer, nullable=False, server_default='0'),
        sa.Column('completed_items', sa.Integer, nullable=False, server_default='0'),
        sa.Column('failed_items', sa.Integer, nullable=False, server_default='0'),
        sa.Column('archive_storage_key', sa.String(255), nullable=True),
        sa.Column('manifest_storage_key', sa.String(255), nullable=True),
        sa.Column('expires_at', sa.DateTime(timezone=True), nullable=True),
        sa.Column('created_at', sa.DateTime(timezone=True), nullable=False),
        sa.Column('updated_at', sa.DateTime(timezone=True), nullable=False),
        sa.Column('deleted_at', sa.DateTime(timezone=True), nullable=True),
    )

def downgrade() -> None:
    op.drop_table('export_jobs')
    op.drop_table('transcript_segments')
    op.drop_table('transcripts')
    op.drop_table('projects')
    op.drop_table('workspace_members')
    op.drop_table('workspaces')
    op.drop_table('users')
