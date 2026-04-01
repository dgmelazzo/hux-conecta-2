-- ============================================================
-- Migration 002: Add permission roles (associado_empresa, colaborador)
-- Run: mysql -u root conecta_crm < migrations/002_add_permission_roles.sql
-- ============================================================

ALTER TABLE usuarios
  MODIFY COLUMN role ENUM('superadmin','gestor','atendente','associado_empresa','colaborador')
  NOT NULL DEFAULT 'gestor';
