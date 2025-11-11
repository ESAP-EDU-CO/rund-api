<?php

/**
 * RUND API - Constantes de Configuración
 * 
 * Todas las constantes utilizadas en el sistema RUND.
 * Separadas en módulos para mejor organización.
 * 
 * @author ESAP Development Team
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Config;

class Config
{
  // ============================================================================
  // CONFIGURACIÓN PRINCIPAL
  // ============================================================================

  /** @var string Usuario de autenticación para OpenKM */
  const USER = "okmAdmin";

  /** @var string Contraseña de autenticación para OpenKM */
  const PASSWORD = "admin";

  /** @var string Endpoint base de la API REST de OpenKM */
  const REST = "/services/rest/";

  /** @var string Directorio temporal para archivos */
  const TEMP_DIR = __DIR__ . "/../../tmp/";

  // ============================================================================
  // SERVICIOS EXTERNOS (AI, OCR)
  // ============================================================================

  /** @var string URL del servicio rund-ai (extracción y análisis) */
  const RUND_AI_URL = "http://rund-ai:8001";

  /** @var string URL base de esta API (para callbacks) */
  const API_BASE_URL = "http://rund-api:3000";

  // ============================================================================
  // ESTRUCTURA DE RUTAS EN OPENKM
  // ============================================================================


  // ----------------------------- RUTAS BASE -----------------------------
  /** @var string Ruta raíz de taxonomía en OpenKM */
  const ROOT_TAX = "/okm:root/RUND/";

  /** @var string Ruta raíz de categorías en OpenKM */
  const ROOT_CTG = "/okm:categories/RUND/";


  // ----------------------------- RUTAS PRINCIPALES -----------------------------
  /** @var string Documentos en taxonomía */
  const ROOT_TAX_DOCS = self::ROOT_TAX . "DOCUMENTOS/";

  /** @var string Documentos en categorías */
  const ROOT_CTG_DOCS = self::ROOT_CTG . "DOCUMENTOS/";

  /** @var string Docentes en taxonomía */
  const ROOT_TAX_PROF = self::ROOT_TAX . "DOCENTES/";

  /** @var string Docentes en categorías */
  const ROOT_CTG_PROF = self::ROOT_CTG . "DOCENTES/";

  /** @var string Configuración en taxonomía */
  const ROOT_TAX_CONF = self::ROOT_TAX . "CONFIG/";

  /** @var string Configuración en categorías */
  const ROOT_CTG_CONF = self::ROOT_CTG . "CONFIG/";


  // ----------------------------- CATEGORÍAS ESPECÍFICAS -----------------------------
  /** @var string Categoría de listados */
  const CTGR_LISTADOS = self::ROOT_CTG_DOCS . "LISTADOS/";

  /** @var string Categoría de firmas */
  const CTGR_FIRMAS = self::ROOT_CTG_DOCS . "FIRMAS/";

  /** @var string Categoría de hojas de vida */
  const CTGR_DOCS_HOJAS = self::ROOT_CTG_DOCS . "HOJAS_DE_VIDA/";

  /** @var string Categoría de imágenes de app */
  const CTGR_CONF_IMG = self::ROOT_CTG_CONF . "IMG/";

  /** @var string Categoría de imágenes de app */
  const CTGR_CONF_DATA = self::ROOT_CTG_CONF . "DATA/";

  /** @var string Categoría de estado de extracción (pendiente/procesando/completado/error) */
  const CTGR_EXTRACTION = self::ROOT_CTG_DOCS . "EXTRACTION_STATUS/";


  // ----------------------------- TAXONOMÍAS ESPECÍFICAS -----------------------------
  /** @var string Taxonomía de firmas */
  const TAX_FIRMAS = self::ROOT_TAX_DOCS . "FIRMAS/";

  /** @var string Taxonomía de listados */
  const TAX_LISTADOS = self::ROOT_TAX_DOCS . "LISTADOS/";

  /** @var string Taxonomía de hojas de vida */
  const TAX_HOJAS = self::ROOT_TAX_PROF . "HOJAS_DE_VIDA/";

  /** @var string Taxonomía de certificados */
  const TAX_CERTIFICADOS = self::ROOT_TAX_DOCS . "CERTIFICADOS/";

  /** @var string Taxonomía de plantillas base */
  const TAX_PLANTILLAS = self::ROOT_TAX_DOCS . "PLANTILLAS/";

  /** @var string Plantillas de certificados */
  const TAX_PLANTILLAS_CERTIFICADOS = self::TAX_PLANTILLAS . "CERTIFICADOS/";

  /** @var string Plantillas de reportes */
  const TAX_PLANTILLAS_REPORTES = self::TAX_PLANTILLAS . "REPORTES/";

  /** @var string Datos de aplicación */
  const TAX_APP_DATA = self::ROOT_TAX_CONF . "DATA/";

  /** @var string Imágenes de aplicación */
  const TAX_APP_IMG = self::ROOT_TAX_CONF . "IMG/";
}
