<?php

/**
 * RUND-API - Servicio de Gestión de Documentos
 * 
 * Maneja operaciones específicas de AI y OCR
 * 
 * @author ESAP Development Team / Oliver Castelblanco Martínez oliver.castelblanco@esap.edu.co
 * @version 2.0
 * @since PHP 8.3
 */

declare(strict_types=1);

namespace RUND\Services;


use RUND\Config\Config as Config;

class AIService
{
  /**
   * analizaDocumento Analiza un documento usando OCR y AI
   * @param string $filePath Ruta del archivo del documento a procesar
   * @param string $tipoDocumento Tipo de documento (ej. "certificado", "contrato", "documento de identidad", "hoja de vida")
   * @param array $datosExtraer Datos a extraer del documento
   * @param string|null $prompt Prompt personalizado para la AI (opcional)
   * @return array Resultado del análisis con OCR y AI
   */
  public static function analizaDocumento(string $filePath, string $tipoDocumento, array $datosExtraer): array
  {
    $ocrResult = self::extraerTextoDeDocumento($filePath);
    unlink($filePath);
    $extractedText = $ocrResult['text'];
    if ($extractedText) {
      $aiPayload = self::construyeAiPayload($tipoDocumento, $datosExtraer, $extractedText);
      $respuestaIA = self::requestAI($aiPayload);
      $aiResult = self::procesarRespuestaIA($respuestaIA['data']);
      $aiResult['ocr_result'] = $ocrResult;
      return $aiResult;
    }
    return $ocrResult;
  }

  /**
   * extraerTextoDeDocumento Extrae con OCR los textos de un documento
   * @param string $filePath Ruta del archivo del documento a procesar
   * @return array Resultado del OCR con el texto extraído
   */
  public static function extraerTextoDeDocumento(string $filePath): array
  {
    $ocrUrl = $_ENV['OCR_API_URL'] . '/extract-text';
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $ocrUrl);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_POST, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 60);
    curl_setopt($curl, CURLOPT_POSTFIELDS, ['file' => new \CURLFile($filePath)]);
    curl_setopt($curl, CURLOPT_HTTPHEADER, ["Accept: application/json"]);
    $resp = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($httpCode === 200) {
      return json_decode($resp, true);
    } else {
      return ["error" => "Error OCR: " . $resp];
    }
  }

  /**
   * Realiza petición a rund-ai con manejo robusto de errores
   *
   * @param array $aiPayload Payload para rund-ai
   * @return array Respuesta procesada con status
   * @throws Exception Si hay errores en la petición
   */
  public static function requestAI(array $aiPayload): array
  {
    $aiUrl = $_ENV['AI_API_URL'] . '/api/generate';
    // Validar que el payload sea un array
    if (!is_array($aiPayload)) {
      throw new \InvalidArgumentException('El payload debe ser un array');
    }
    // Validar campos requeridos
    $camposRequeridos = ['model', 'prompt'];
    foreach ($camposRequeridos as $campo) {
      if (!isset($aiPayload[$campo]) || empty($aiPayload[$campo])) {
        throw new \InvalidArgumentException("Campo requerido faltante: {$campo}");
      }
    }
    // Codificar JSON con flags específicos para evitar problemas de encoding
    $jsonPayload = json_encode($aiPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception('Error al codificar JSON: ' . json_last_error_msg());
    }
    // Configurar cURL
    $curl = curl_init();
    $curlOptions = [
      CURLOPT_URL => $aiUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_POST => true,
      CURLOPT_TIMEOUT => 480, // Aumentado para documentos complejos
      CURLOPT_CONNECTTIMEOUT => 30,
      CURLOPT_POSTFIELDS => $jsonPayload,
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Content-Length: ' . strlen($jsonPayload),
        'Accept: application/json'
      ],
      CURLOPT_SSL_VERIFYPEER => false, // Solo si usas HTTPS local
      CURLOPT_FOLLOWLOCATION => true,
      CURLOPT_MAXREDIRS => 3,
    ];
    curl_setopt_array($curl, $curlOptions);
    // Ejecutar petición
    $aiResult = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $curlError = curl_error($curl);
    $curlInfo = curl_getinfo($curl);
    curl_close($curl);
    // Verificar errores de cURL
    if ($aiResult === false) {
      throw new \Exception("Error de cURL: {$curlError}");
    }
    // Procesar respuesta según código HTTP
    if ($httpCode !== 200) {
      return [
        'success' => false,
        'error' => "Error HTTP {$httpCode}",
        'message' => $aiResult,
        'http_code' => $httpCode
      ];
    }
    // Decodificar respuesta JSON
    $decodedResult = json_decode($aiResult, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      return [
        'success' => false,
        'error' => 'Error al decodificar respuesta JSON: ' . json_last_error_msg(),
        'raw_response' => $aiResult
      ];
    }
    return [
      'success' => true,
      'data' => $decodedResult,
      'http_code' => $httpCode
    ];
  }

  /**
   * Función específica para extracción de datos con reintentos
   */
  public static function extraerDatosIA(
    string $tipoDocumento,
    array $datosExtraer,
    string $extractedText,
    int $maxReintentos = 3
  ): array {
    $intento = 1;
    while ($intento <= $maxReintentos) {
      try {
        error_log("Intento {$intento} de extracción para documento: {$tipoDocumento}");
        // Construir payload
        $payload = self::construyeAiPayload($tipoDocumento, $datosExtraer, $extractedText);
        // Hacer petición
        $response = self::requestAI($payload);
        if (!$response['success']) {
          throw new \Exception("Error en petición: " . $response['error']);
        }
        // Procesar respuesta específica de rund-ai
        $resultado = self::procesarRespuestaIA($response['data']);
        if ($resultado['success']) {
          error_log("Extracción exitosa en intento {$intento}");
          return $resultado;
        }
        // Si no fue exitoso pero no es error crítico, reintenta
        error_log("Fallo en procesamiento, reintentando... " . $resultado['error']);
        $intento++;
        // Esperar antes del siguiente intento
        if ($intento <= $maxReintentos) {
          sleep(2);
        }
      } catch (\Exception $e) {
        error_log("Error en intento {$intento}: " . $e->getMessage());
        if ($intento === $maxReintentos) {
          return [
            'success' => false,
            'error' => "Falló después de {$maxReintentos} intentos: " . $e->getMessage(),
            'intento_fallido' => $intento
          ];
        }
        $intento++;
        sleep(2);
      }
    }
    return [
      'success' => false,
      'error' => "Agotados todos los reintentos ({$maxReintentos})"
    ];
  }

  /**
   * Función para validar la conexión con rund-ai
   */
  public static function validarConexionIA(): array
  {
    $aiUrl = $_ENV['AI_API_URL'] . '/api/tags';
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => $aiUrl,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT => 10,
      CURLOPT_HTTPHEADER => ['Accept: application/json']
    ]);
    $result = curl_exec($curl);
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    if ($httpCode === 200) {
      $models = json_decode($result, true);
      return [
        'success' => true,
        'models' => $models['models'] ?? [],
        'message' => 'Conexión exitosa con rund-ai'
      ];
    } else {
      return [
        'success' => false,
        'error' => "No se puede conectar a rund-ai (HTTP {$httpCode})",
        'response' => $result
      ];
    }
  }

  /**
   * Construye el payload para rund-ai para extracción de datos de documentos
   * @param string $tipoDocumento Tipo de documento a procesar
   * @param array $datosExtraer Array con la estructura de datos a extraer
   * @param string $extractedText Texto extraído por rund-ocr
   * @param float $temp Temperatura para la generación (0.0-1.0)
   * @param float $top Top-p para la generación (0.0-1.0)
   * @param int $numPredict Número máximo de tokens a generar
   * @param array $stopTokens Tokens de parada opcionales
   * @return array Payload completo para rund-ai
   * @throws InvalidArgumentException Si los parámetros no son válidos
   */
  public static function construyeAiPayload(
    string $tipoDocumento,
    array $datosExtraer,
    string $extractedText,
    float $temp = 0.1,
    float $top = 0.9,
    int $numPredict = 2048,
    array $stopTokens = [],
  ): array {
    // Validación de parámetros
    if (empty($tipoDocumento)) {
      throw new \InvalidArgumentException('El tipo de documento no puede estar vacío');
    }
    if (empty($datosExtraer)) {
      throw new \InvalidArgumentException('Los datos a extraer no pueden estar vacíos');
    }
    if (empty(trim($extractedText))) {
      throw new \InvalidArgumentException('El texto extraído no puede estar vacío');
    }
    if ($temp < 0.0 || $temp > 1.0) {
      throw new \InvalidArgumentException('La temperatura debe estar entre 0.0 y 1.0');
    }
    if ($top < 0.0 || $top > 1.0) {
      throw new \InvalidArgumentException('El valor top_p debe estar entre 0.0 y 1.0');
    }
    if ($numPredict < 1) {
      throw new \InvalidArgumentException('El número de predicciones debe ser mayor a 0');
    }
    // Limpiar y preparar el texto
    $cleanedText = self::limpiarTextoOCR($extractedText);
    // Obtener descripción de campos específica para el tipo de documento
    $fieldsDescription = self::obtenerDescripcionCampos($tipoDocumento, $datosExtraer);
    // Construir el prompt estructurado a menos que se haya indicado un prompt
    $prompt = self::construirPromptEstructurado($tipoDocumento, $cleanedText, $fieldsDescription, $datosExtraer);
    // Agregar tokens de parada específicos para JSON
    if (empty($stopTokens)) {
      $stopTokens = self::obtenerStopTokensPorTipo($tipoDocumento);
    }
    // Construir payload base
    $aiPayload = [
      //'model' => 'phi3:mini',
      'model' => 'mistral',
      'prompt' => $prompt,
      'stream' => false,
      'options' => [
        'temperature' => $temp,
        'top_p' => $top,
        'num_predict' => $numPredict,
        'repeat_penalty' => 1.1,
        'top_k' => 40,
        'stop' => $stopTokens,
      ],
    ];
    return $aiPayload;
  }

  // Genera, a partir del tipo de documento, una serie de stop tokens
  public static function obtenerStopTokensPorTipo(string $tipoDocumento): array
  {
    $stopTokensGenericos = [
      //'```',
      '\n\nNota:',
      '\n\nImportante:',
      'Análisis adicional:',
      'Comentarios extra:',
    ];
    $stopTokensEspecificos = [
      'documento_identidad' => [
        'Verificación adicional:',
        'Datos complementarios:',
      ],
      'hoja_vida' => [
        'Recomendaciones:',
        'Sugerencias:',
      ],
      // ... más tipos
    ];
    $especificos = $stopTokensEspecificos[$tipoDocumento] ?? [];
    return array_merge($stopTokensGenericos, $especificos);
  }

  /**
   * Construye el prompt estructurado para la extracción de datos
   */
  public static function construirPromptEstructurado(string $tipoDocumento, string $cleanedText, string $fieldsDescription, array $datosExtraer): string
  {
    $jsonStructure = json_encode($datosExtraer, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    $prompt = <<<PROMPT
        Eres un experto en análisis de documentos académicos colombianos. Tu tarea es extraer información específica de textos obtenidos mediante OCR.

        **TIPO DE DOCUMENTO:** {$tipoDocumento}

        **TEXTO DEL DOCUMENTO:**
        {$cleanedText}

        **DATOS A EXTRAER:**
        {$fieldsDescription}

        **ESTRUCTURA JSON ESPERADA:**
        {$jsonStructure}

        **INSTRUCCIONES CRÍTICAS:**
        1. Analiza cuidadosamente el texto proporcionado
        2. Extrae únicamente los datos solicitados de la estructura JSON
        3. Si un dato no se encuentra en el texto, usa null (no string "null")
        4. Para fechas, usa formato DD/MM/AAAA cuando sea posible
        5. Para arrays, incluye todos los elementos encontrados en el texto
        6. Mantén la precisión en nombres, números de documento y fechas
        7. Si encuentras abreviaciones comunes (ej: "Nal" = "Nacional"), expándelas
        8. Responde ÚNICAMENTE con un JSON válido, sin texto adicional antes o después
        9. No incluyas anotaciones o comentarios en el JSON; evita comentarios como // No se menciona en el texto, por lo que es nulo

        **FORMATO DE RESPUESTA EXACTO:**
        ```json
        {
        "status": "success",
        "tipo_documento": "{$tipoDocumento}",
        "datos_extraidos": {
          // Estructura completa con los datos extraídos
        },
        "confianza": "alta|media|baja",
        "observaciones": "comentarios si hay inconsistencias o datos poco claros"
        }
        ```

        Responde ahora con el JSON:
        PROMPT;

    return $prompt;
  }

  /**
   * Obtiene la descripción específica de campos según el tipo de documento
   */
  public static function obtenerDescripcionCampos(string $tipoDocumento, array $datosExtraer): string
  {
    $descripciones = [
      'documento_identidad' => [
        'nombres' => 'Nombres completos de la persona (solo nombres, no apellidos)',
        'apellidos' => 'Apellidos completos de la persona (solo apellidos, no nombres)',
        'numero_documento' => 'Número de identificación (solo números, sin puntos ni espacios)',
        'fecha_nacimiento' => 'Fecha de nacimiento en formato DD/MM/AAAA',
        'fecha_expedicion' => 'Fecha de expedición del documento en formato DD/MM/AAAA',
        'lugar_nacimiento' => 'Ciudad y/o departamento de nacimiento',
        'lugar_expedicion' => 'Ciudad y/o departamento donde se expidió el documento'
      ],

      'hoja_vida' => [
        'datos_personales' => 'Información personal: nombres, apellidos, documento, teléfono, email, dirección',
        'formacion_academica' => 'Array con estudios: título, institución, año de graduación, nivel académico',
        'experiencia_laboral' => 'Array con trabajos: cargo, empresa, fecha inicio, fecha fin, funciones principales',
        'idiomas' => 'Array con idiomas: idioma, nivel (básico/intermedio/avanzado/nativo)',
        'referencias' => 'Array con referencias: nombre completo, cargo, teléfono, email, empresa'
      ],

      'certificado_experiencia_laboral' => [
        'empleado' => 'Datos del empleado: nombres, apellidos, número de documento',
        'empresa' => 'Datos de la empresa: nombre/razón social, NIT, representante legal',
        'experiencia' => 'Detalles del empleo: cargo, fechas de inicio y fin, duración, tipo de contrato, funciones realizadas'
      ],

      'certificado_experiencia_docente' => [
        'docente' => 'Datos del docente: nombres, apellidos, documento',
        'institucion' => 'Datos de la institución: nombre, tipo (universidad/colegio/instituto), NIT',
        'experiencia_docente' => 'Detalles docentes: asignaturas, programas, fechas, modalidad, nivel educativo'
      ],

      'articulo_productividad' => [
        'articulo' => 'Datos del artículo: título, autores, revista, ISSN, año, volumen, número, páginas, DOI',
        'clasificacion' => 'Clasificación académica: categoría Minciencias, área de conocimiento, tipo de artículo'
      ],

      'certificado_investigacion' => [
        'investigador' => 'Datos del investigador: nombres, apellidos, documento',
        'proyecto' => 'Datos del proyecto: título, código, entidad financiadora, fechas, presupuesto, rol',
        'clasificacion' => 'Clasificación: área de conocimiento, grupo de investigación, línea de investigación'
      ],

      'certificado_idiomas' => [
        'estudiante' => 'Datos del estudiante: nombres, apellidos, documento',
        'certificacion' => 'Datos del certificado: idioma, nivel, marco de referencia, puntaje, fechas, institución',
        'habilidades' => 'Niveles por habilidad: lectura, escritura, escucha, habla'
      ],

      'certificado_estudios_no_formales' => [
        'participante' => 'Datos del participante: nombres, apellidos, documento',
        'curso' => 'Datos del curso: nombre, tipo, área, intensidad horaria, fechas, modalidad',
        'institucion' => 'Datos de la institución: nombre, tipo, registro oficial',
        'certificacion' => 'Datos del certificado: número, fecha expedición, vigencia'
      ]
    ];
    $tipoNormalizado = strtolower(str_replace([' ', '-'], '_', $tipoDocumento));
    $descripcionCampos = $descripciones[$tipoNormalizado] ?? [];
    if (empty($descripcionCampos)) {
      // Descripción genérica si no se encuentra el tipo específico
      return "Extrae todos los campos especificados en la estructura JSON proporcionada";
    }
    $descripcionTexto = "";
    foreach ($descripcionCampos as $campo => $descripcion) {
      $descripcionTexto .= "- {$campo}: {$descripcion}\n";
    }
    return trim($descripcionTexto);
  }

  /**
   * Limpia y normaliza el texto extraído por OCR
   */
  public static function limpiarTextoOCR(string $texto): string
  {
    // Eliminar caracteres de control y espacios extra
    $texto = preg_replace('/[\x00-\x1F\x7F]/u', '', $texto);
    $texto = preg_replace('/\s+/', ' ', $texto);
    // Corregir caracteres comunes mal interpretados por OCR
    $correcciones = [
      'l' => '1',  // En contextos numéricos
      'O' => '0',  // En contextos numéricos
      '|' => '1',  // Pipes interpretados como 1
      'º' => '°',  // Símbolos de grado
      '№' => 'No.', // Número
      'С' => 'C',  // C cirílica por C latina
    ];
    // Aplicar correcciones contextuales
    foreach ($correcciones as $incorrecto => $correcto) {
      $texto = str_replace($incorrecto, $correcto, $texto);
    }
    // Normalizar espacios y saltos de línea
    $texto = preg_replace('/\n\s*\n/', "\n\n", $texto); // Dobles saltos
    $texto = trim($texto);
    return $texto;
  }

  /**
   * Procesa la respuesta de rund-ai
   */
  public static function procesarRespuestaIA(array $response): array
  {
    try {
      if (!isset($response['response'])) {
        throw new \Exception('Respuesta de API inválida: falta campo response');
      }
      $responseText = $response['response'];
      // Extraer JSON de la respuesta
      $jsonData = self::extraerJsonDeRespuesta($responseText);
      // Validar estructura básica
      if (!isset($jsonData['status']) || !isset($jsonData['datos_extraidos'])) {
        throw new \Exception('Estructura de respuesta JSON inválida');
      }
      return [
        'success' => true,
        'error' => null,
        'data' => $jsonData,
        'raw_response' => $responseText
      ];
    } catch (\Exception $e) {
      return [
        'success' => false,
        'error' => $e->getMessage(),
        'raw_response' => $response['response'] ?? 'No response'
      ];
    }
  }

  /**
   * Extrae JSON válido de la respuesta del modelo
   */
  public static function extraerJsonDeRespuesta(string $response): array
  {
    // Buscar el primer { y el último }
    $startPos = strpos($response, '{');
    $endPos = strrpos($response, '}');
    if ($startPos === false || $endPos === false || $startPos >= $endPos) {
      throw new \Exception('No se encontró JSON válido en la respuesta');
    }
    $jsonString = substr($response, $startPos, $endPos - $startPos + 1);
    // Intentar decodificar JSON
    $decoded = json_decode($jsonString, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      throw new \Exception('Error al decodificar JSON: ' . json_last_error_msg());
    }
    return $decoded;
  }
}
