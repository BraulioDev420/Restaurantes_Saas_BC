# 🍽️ SABOREA – Sistema SaaS de Gestión para Restaurantes

Plataforma web multi-restaurante en desarrollo orientada a la administración integral de negocios gastronómicos, permitiendo gestionar catálogos, operaciones y paneles de control independientes por establecimiento bajo una arquitectura escalable.

## 🚧 Estado del proyecto
* **Fase actual:** En desarrollo activo (Módulos de Autenticación, Roles, Superadministrador y Gestión de Categorías implementados).

---

## 🛠️ Stack Tecnológico

### Frontend
* **Angular** (Versión 21)
* **TypeScript**
* **Bootstrap 5 & SCSS**

### Backend & Arquitectura
* **PHP & Laravel**
* **Laravel Sanctum** (Autenticación API REST segura)
* **Control de Acceso Basado en Roles (RBAC)**

### Base de Datos & Herramientas
* **MySQL** (Bases de datos relacionales)
* **Git & GitHub** (Control de versiones)
* **Postman** (Pruebas de endpoints)

---

## 🚀 Funcionalidades Principales Implementadas
1. **Autenticación Segura:** Inicio de sesión y gestión de sesiones mediante tokens con Laravel Sanctum.
2. **Control de Accesos y Roles (RBAC):** Jerarquía de usuarios (Superadministrador, Administrador de Restaurante, Empleados).
3. **Panel de Superadministrador:** Creación, supervisión y administración global de múltiples restaurantes en la plataforma SaaS.
4. **Mantenimiento de Categorías:** CRUD completo asociado de manera independiente y segura a cada restaurante.
5. **Aislamiento de Datos (Multi-tenant):** Separación estricta de información para que cada establecimiento gestione únicamente sus recursos.

---

## 🏗️ Estructura del Repositorio
El proyecto está estructurado como un monorepo dividido en dos entornos independientes:
* 📂 `/backend`: API REST desarrolladas bajo buenas prácticas en Laravel.
* 📂 `/frontend`: Aplicación modular desarrollada en Angular.

---
*Proyecto personal desarrollado por **Braulio Castro** (Ingeniería de Sistemas).*
