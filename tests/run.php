<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../public/scripts/bitacora_helpers.php';
require_once __DIR__ . '/../public/scripts/bitacora_mail_helpers.php';
require_once __DIR__ . '/../public/scripts/bitacora_download_helpers.php';
require_once __DIR__ . '/../public/scripts/bitacora_validation_helpers.php';
require_once __DIR__ . '/../public/config/security.php';
require_once __DIR__ . '/../public/config/admin_formulario_helpers.php';
require_once __DIR__ . '/../public/config/admin_destinatarios_helpers.php';
require_once __DIR__ . '/../public/config/bitacora_drafts.php';
require_once __DIR__ . '/../public/vistas/bitacora_view_helpers.php';
require_once __DIR__ . '/../scripts/process_bitacora_email_queue.php';

function test_assert_same($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, $label . ' fallo. Esperado: ' . var_export($expected, true) . ' Actual: ' . var_export($actual, true) . PHP_EOL);
        exit(1);
    }
}

function test_assert_throws(callable $callback, string $expectedClass, string $label): void
{
    try {
        $callback();
    } catch (Throwable $e) {
        test_assert_same($expectedClass, get_class($e), $label);
        return;
    }
    fwrite(STDERR, $label . ' fallo. No lanzó excepción.' . PHP_EOL);
    exit(1);
}

test_assert_same('A_B_C', bit_safe_filename('A/B C'), 'bit_safe_filename');
test_assert_same('a, b', bit_normalize_array_value([' a ', '', 'b']), 'bit_normalize_array_value');
test_assert_same(45, bit_calcular_minutos_planta('23:30', '00:15'), 'bit_calcular_minutos_planta');
test_assert_same('2026-07-31', bit_normalize_pdf_date('31-07-2026'), 'bit_normalize_pdf_date d-m-Y');
test_assert_same('2026-07-31', bit_normalize_pdf_date('2026-07-31'), 'bit_normalize_pdf_date Y-m-d');
test_assert_same('Sin novedad', bit_report_display_value('No'), 'report exact No value');
test_assert_same('No se reportaron casos', bit_report_display_value('No se reportaron casos'), 'report custom No phrase');
test_assert_same(
    ['to' => ['primary@example.test'], 'cc' => ['copy@example.test'], 'bcc' => []],
    app_bitacora_normalize_recipients([
        'to' => ['primary@example.test', 'PRIMARY@example.test'],
        'cc' => ['copy@example.test', 'primary@example.test'],
        'bcc' => ['copy@example.test'],
    ]),
    'recipient normalization removes cross-type duplicates'
);
test_assert_same(
    ['to' => ['primary@example.test'], 'cc' => ['copy@example.test'], 'bcc' => []],
    app_bitacora_filter_full_recipients([
        'to' => ['primary@example.test', 'section@example.test'],
        'cc' => ['copy@example.test'],
        'bcc' => ['SECTION@example.test'],
    ], [[
        'email' => 'section@example.test',
        'sections' => ['operaciones'],
    ]]),
    'section recipients are excluded from full email'
);
test_assert_same('admin@example.test', bit_admin_validate_email(' Admin@Example.Test '), 'recipient email normalization');
test_assert_same('CCO', bit_admin_recipient_type_options()['bcc'] ?? null, 'recipient type labels');
test_assert_same(
    ['base' => 0, 'operaciones' => 1],
    bit_admin_section_order(['base' => 'Datos básicos', 'operaciones' => 'Operaciones']),
    'section order follows form schema'
);
test_assert_throws(
    static fn() => bit_admin_validate_email('not-an-email'),
    InvalidArgumentException::class,
    'invalid recipient email rejected'
);
test_assert_same('No se enviaron bolsas a otras sedes', bit_report_yes_no_value('No', 'No se enviaron bolsas a otras sedes'), 'negative answer uses detail only');
test_assert_same('Sin novedad', bit_report_yes_no_value('No'), 'negative answer without detail');
test_assert_same('No hubo novedades', bit_report_yes_no_value('No', '', 'No hubo novedades'), 'negative answer uses configured report value');
test_assert_same('Si. Detalle', bit_report_yes_no_value('Si', 'Detalle'), 'positive answer keeps detail prefix');
test_assert_same('$123.456', bit_report_format_number('123456', ['type' => 'number', 'number_format' => 'currency']), 'currency number report format');
test_assert_same('$123.456,78', bit_report_format_number('123456.78', ['type' => 'number', 'number_format' => 'currency', 'number_decimals' => 2]), 'currency decimal report format');
test_assert_same('3 unidades', bit_report_field_value('3', ['type' => 'number', 'suffix' => 'unidades']), 'number suffix report format');
test_assert_same('1 unidad', bit_report_field_value('1', ['type' => 'number', 'suffix' => 'unidades', 'suffix_singular' => 'unidad']), 'number singular suffix report format');
test_assert_same('3 unidades', bit_report_field_value('3', ['type' => 'number', 'suffix' => 'unidades', 'suffix_singular' => 'unidad']), 'number plural suffix report format');
test_assert_same(true, strpos(bit_render_detail('Estado', 'No'), 'Sin novedad') !== false, 'report detail exact No value');

$defaultTexts = bit_get_default_texts();
foreach (['DEFAULT_TI3', 'DEFAULT_GH', 'DEFAULT_MANT', 'DEFAULT_BONOS', 'DEFAULT_RESERVAS', 'DEFAULT_EASYPEDIDO'] as $removedDefault) {
    test_assert_same(false, array_key_exists($removedDefault, $defaultTexts), 'removed default ' . $removedDefault);
}
$conditionalRules = bit_get_conditional_rules($defaultTexts);
$conditionalRadios = array_map(static fn($rule) => (string) ($rule['radio'] ?? ''), $conditionalRules);
foreach (['casos_ti', 'casos_sst', 'equipos_cocina', 'equipos_bar', 'equipos_salon', 'locativos', 'pendientes', 'bonos_coomeva', 'reservas_15', 'easypedido'] as $removedRadio) {
    test_assert_same(false, in_array($removedRadio, $conditionalRadios, true), 'removed conditional radio ' . $removedRadio);
}
$novedadesSstRule = array_values(array_filter($conditionalRules, static fn($rule) => ($rule['radio'] ?? '') === 'novedades_sst'));
test_assert_same('sst8', $novedadesSstRule[0]['field'] ?? null, 'current SST detail field');
$defaultedConditionalPost = ['visita_west' => 'No'];
$defaultedConditionalFields = bit_apply_conditional_defaults($defaultedConditionalPost, $conditionalRules);
test_assert_same(['visita_west'], $defaultedConditionalFields, 'conditional default tracks source field');
test_assert_same($defaultTexts['DEFAULT_BPM'], $defaultedConditionalPost['bpm3'] ?? null, 'conditional default uses configured text');

$subsection = app_bitacora_normalize_dynamic_field([
    'type' => 'subsection',
    'name' => 'datos_equipo',
    'label' => 'Datos del equipo',
    'description' => "Describe el estado.\nIncluye novedades.",
    'required' => true,
]);
test_assert_same('subsection', $subsection['type'] ?? null, 'subsection normalization type');
test_assert_same(false, $subsection['required'] ?? null, 'subsection normalization is not required');
test_assert_same('col-md-12', $subsection['col'] ?? null, 'subsection normalization full width');
test_assert_same(
    ['campo_real'],
    app_bitacora_collect_field_names([
        ['fields' => [$subsection, ['type' => 'text', 'name' => 'campo_real']]],
    ]),
    'subsection excluded from submitted field names'
);

$fixedSubsection = app_bitacora_subsection('base_datos_equipo', 'Datos base', 'Descripción base');
test_assert_same('subsection', $fixedSubsection['type'], 'fixed subsection helper');
test_assert_same([], bit_draft_field_definitions([['fields' => [$fixedSubsection]]]), 'subsection excluded from drafts');
$_POST = [];
test_assert_same([true, ''], bit_validate_schema_fields([['fields' => [$subsection]]]), 'subsection excluded from validation');

ob_start();
bit_view_render_field($subsection);
$subsectionFormHtml = (string) ob_get_clean();
test_assert_same(true, strpos($subsectionFormHtml, 'Datos del equipo') !== false, 'subsection rendered in form');
test_assert_same(true, strpos($subsectionFormHtml, 'Describe el estado.<br') !== false, 'subsection description rendered in form');

$reportSections = [[
    'key' => 'operaciones',
    'title' => 'OPERACIONES',
    'fields' => [
        ['type' => 'text', 'name' => 'antes', 'label' => 'Antes'],
        ['type' => 'text', 'name' => 'estado', 'label' => 'Estado'],
        ['type' => 'yes_no', 'name' => 'hielo_enviado', 'label' => '¿Se ha enviado hielo?', 'detail_name' => 'hielo_enviado_detalle'],
        $subsection,
        ['type' => 'text', 'name' => 'despues', 'label' => 'Después'],
    ],
]];
$reportHtml = bit_render_html([
    'sede' => 'PANCE',
    'fecha' => '05-08-2026',
    'fecha_iso' => '2026-08-05',
    'responsable' => 'Prueba',
    'cargo' => 'Pruebas',
    'antes' => 'Valor anterior',
    'estado' => 'No',
    'hielo_enviado' => 'No',
    'hielo_enviado_detalle' => 'No se enviaron bolsas a otras sedes',
    'despues' => 'Valor posterior',
], [
    'sections' => [],
    'form_sections' => $reportSections,
    'dynamic_fields' => [$subsection],
]);
$beforePosition = strpos($reportHtml, 'Valor anterior');
$subsectionPosition = strpos($reportHtml, 'Datos del equipo');
$afterPosition = strpos($reportHtml, 'Valor posterior');
test_assert_same(true, $beforePosition !== false && $subsectionPosition > $beforePosition && $afterPosition > $subsectionPosition, 'subsection report order');
test_assert_same(true, strpos($reportHtml, 'Describe el estado.<br') !== false, 'subsection description rendered in report');
test_assert_same(true, strpos($reportHtml, 'Estado:</strong> Sin novedad') !== false, 'PDF report exact No value');
test_assert_same(true, strpos($reportHtml, '¿Se ha enviado hielo?:</strong> No se enviaron bolsas a otras sedes') !== false, 'PDF negative answer uses detail only');
test_assert_same(false, strpos($reportHtml, '¿Se ha enviado hielo?:</strong> No. ') !== false, 'PDF negative answer has no No prefix');
$reportPdfHtml = bit_render_html([
    'sede' => 'PANCE',
    'fecha' => '05-08-2026',
    'fecha_iso' => '2026-08-05',
    'responsable' => 'Prueba',
    'cargo' => 'Pruebas',
    'antes' => 'Valor anterior',
    'estado' => 'No',
    'hielo_enviado' => 'No',
    'hielo_enviado_detalle' => 'No se enviaron bolsas a otras sedes',
    'despues' => 'Valor posterior',
], [
    'form_sections' => $reportSections,
], true);
test_assert_same(true, strpos($reportPdfHtml, 'margin: 0;') !== false, 'PDF removes duplicate body margin');
test_assert_same(true, strpos($reportPdfHtml, 'page-break-inside: auto;') !== false, 'PDF allows sections to split');
test_assert_same(true, strpos($reportPdfHtml, 'page-break-after: avoid;') !== false, 'PDF keeps section title with content');
test_assert_same(true, substr_count($reportPdfHtml, 'class="report-block"') >= 2, 'PDF preserves logical report blocks');

$humanReportSections = [[
    'key' => 'gestion_humana',
    'title' => 'GESTIÓN HUMANA',
    'fields' => [
        app_bitacora_yes_no_quantity_group_field(
            'gh_vacantes_abiertas',
            '¿SE CUENTA CON VACANTES ABIERTAS ACTUALMENTE?',
            'gh_vacantes_cantidad',
            'Cantidad',
            [['type' => 'text', 'name' => 'cargo', 'label' => 'Cargo']],
            ['no_report_value' => 'No se tienen vacantes pendientes.']
        ),
        app_bitacora_yes_no_quantity_group_field(
            'gh_vacaciones',
            '¿HAY NOVEDADES DEL PERSONAL POR VACACIONES?',
            'gh_vacaciones_cantidad',
            'Cantidad',
            [['type' => 'text', 'name' => 'colaborador', 'label' => 'Colaborador']],
            ['no_report_value' => 'Sin novedad alguna.']
        ),
        app_bitacora_yes_no_detail_group_field(
            'gh_reingreso_vacaciones',
            '¿HUBO REINGRESO DEL PERSONAL EN VACACIONES?',
            [['type' => 'text', 'name' => 'colaborador', 'label' => 'Colaborador']],
            ['no_report_value' => 'No se presento ningun reingreso.']
        ),
        app_bitacora_yes_no_quantity_group_field(
            'gh_ingreso_personal',
            '¿HUBO INGRESO DE PERSONAL NUEVO?',
            'gh_ingreso_personal_cantidad',
            'Cantidad',
            [['type' => 'text', 'name' => 'colaborador', 'label' => 'Colaborador']],
            ['no_report_value' => 'No por el dia de hoy.']
        ),
    ],
]];
$humanReportHtml = bit_render_html([
    'sede' => 'PANCE',
    'fecha' => '05-08-2026',
    'fecha_iso' => '2026-08-05',
    'responsable' => 'Prueba',
    'cargo' => 'Pruebas',
    'gh_vacantes_abiertas' => 'No',
    'gh_vacaciones' => 'No',
    'gh_reingreso_vacaciones' => 'No',
    'gh_ingreso_personal' => 'No',
], ['form_sections' => $humanReportSections]);
test_assert_same(false, strpos($humanReportHtml, 'GESTIÓN HUMANA') !== false, 'section with only default No answers is omitted');
test_assert_same(false, strpos($humanReportHtml, 'No se tienen vacantes pendientes.') !== false, 'default No text is omitted');

$improvementReportSections = [[
    'key' => 'mejoramiento',
    'title' => 'MEJORAMIENTO Y ESTANDARIZACIÓN (CALIDAD Y AMBIENTAL)',
    'fields' => [
        app_bitacora_yes_no_field('visita_ss', '¿HUBO VISITA DE LA SECRETARÍA DE SALUD?', 'visitaSsGroup', 'bpm1', 'Detalle', 'textarea', ['no_report_value' => 'No se recibio ninguna visita.']),
        app_bitacora_yes_no_field('visita_dagma', '¿HUBO VISITA DEL DAGMA?', 'visitaDagmaGroup', 'bpm2', 'Detalle', 'textarea', ['no_report_value' => 'No se recibio ninguna visita.']),
        app_bitacora_yes_no_field('visita_west', '¿HUBO VISITA DEL PROVEEDOR WEST QUIMICA?', 'visitaWestGroup', 'bpm3', 'Detalle', 'textarea', ['no_report_value' => 'El dia de hoy no se recibio ninguna visita.']),
        app_bitacora_yes_no_field('visita_cp', '¿HUBO VISITA DEL PROVEEDOR DE CONTROL DE PLAGAS?', 'visitaCpGroup', 'bpm4', 'Detalle', 'textarea', ['no_report_value' => 'El dia de hoy no se fumigo.']),
        app_bitacora_yes_no_field('visita_acu', '¿HUBO VISITA DEL PROVEEDOR QUE RECOGE EL ACU?', 'visitaAcuGroup', 'bpm5', 'Detalle', 'textarea', ['no_report_value' => 'El dia de hoy no se realizo ninguna entrega.']),
    ],
]];
$improvementReportHtml = bit_render_html([
    'sede' => 'PANCE',
    'fecha' => '05-08-2026',
    'fecha_iso' => '2026-08-05',
    'responsable' => 'Prueba',
    'cargo' => 'Pruebas',
    'visita_ss' => 'No',
    'bpm1' => 'No se recibio ninguna visita.',
    'visita_dagma' => 'No',
    'bpm2' => 'No se recibio ninguna visita.',
    'visita_west' => 'No',
    'bpm3' => 'El dia de hoy no se recibio ninguna visita.',
    'visita_cp' => 'No',
    'bpm4' => 'El dia de hoy no se fumigo.',
    'visita_acu' => 'Si',
    'bpm5' => 'se llevan 5 bidones, 8 kilos, no dejan recomendaciones',
    '_conditional_default_fields' => ['visita_ss', 'visita_dagma', 'visita_west', 'visita_cp'],
], ['form_sections' => $improvementReportSections]);
test_assert_same(true, strpos($improvementReportHtml, 'MEJORAMIENTO Y ESTANDARIZACIÓN (CALIDAD Y AMBIENTAL)') !== false, 'section with a positive answer is retained');
test_assert_same(true, strpos($improvementReportHtml, '¿HUBO VISITA DEL PROVEEDOR QUE RECOGE EL ACU?') !== false, 'positive conditional question is retained');
test_assert_same(true, strpos($improvementReportHtml, 'Si. se llevan 5 bidones, 8 kilos, no dejan recomendaciones') !== false, 'positive conditional detail is retained');
test_assert_same(false, strpos($improvementReportHtml, 'No se recibio ninguna visita.') !== false, 'default No rows are omitted from mixed section');
test_assert_same(false, strpos($improvementReportHtml, 'No se fumigo.') !== false, 'default No rows are omitted from mixed section');

$noApplyField = app_bitacora_multiselect_detail_group_field(
    'visitas_sin_aplicar',
    'INGRESE LAS PERSONAS DE LAS DISTINTAS ÁREAS QUE VISITARON LA SEDE',
    ['No se tuvieron visitas el dia de hoy' => 'No se tuvieron visitas el dia de hoy']
);
$noApplyRows = bit_render_multiselect_detail_group($noApplyField, [
    '_multiselect_detail_groups' => [
        'visitas_sin_aplicar' => ['no_apply' => true, 'items' => []],
    ],
]);
test_assert_same([], $noApplyRows, 'no-apply visits are omitted from report');
$visitorField = app_bitacora_multiselect_detail_group_field(
    'visitas_con_detalle',
    'VISITAS CON DETALLE',
    ['Área técnica' => 'Área técnica']
);
$visitorRows = bit_render_multiselect_detail_group($visitorField, [
    '_multiselect_detail_groups' => [
        'visitas_con_detalle' => [
            'no_apply' => false,
            'items' => [[
                'visitante' => 'Área técnica',
                'hora_inicio' => '08:00',
                'hora_final' => '09:00',
                'actividades' => 'Actividad de prueba',
            ]],
        ],
    ],
]);
test_assert_same(true, strpos(implode('', $visitorRows), 'report-visitor') !== false, 'PDF protects each visitor block');
$emptyDirectQuantityField = app_bitacora_quantity_group_field(
    'actividades_sin_datos',
    'ACTIVIDADES/CAMPAÑAS',
    'actividades_sin_datos_cantidad',
    'Cantidad',
    [['type' => 'text', 'name' => 'actividad', 'label' => 'Actividad']],
    ['zero_report_value' => 'No se realizaron actividades.']
);
$emptyDefaultSections = [[
    'key' => 'defaults',
    'title' => 'DEFAULTS',
    'fields' => [$noApplyField, $emptyDirectQuantityField],
]];
$emptyDefaultHtml = bit_render_html([
    'sede' => 'PANCE',
    'fecha' => '05-08-2026',
    'fecha_iso' => '2026-08-05',
    'responsable' => 'Prueba',
    'cargo' => 'Pruebas',
    '_multiselect_detail_groups' => [
        'visitas_sin_aplicar' => ['no_apply' => true, 'items' => []],
    ],
    'actividades_sin_datos_cantidad' => '0',
], ['form_sections' => $emptyDefaultSections]);
test_assert_same(false, strpos($emptyDefaultHtml, 'DEFAULTS') !== false, 'section with no-apply and zero defaults is omitted');

$plantPost = ['planta_elect' => 'No', 'novedades_planta' => 'No'];
$plantDefaultFields = bit_handle_planta_electrica($plantPost, $defaultTexts);
test_assert_same(true, in_array('novedades_planta', $plantDefaultFields, true), 'plant default tracks No detail source');
$plantNoField = app_bitacora_yes_no_field(
    'novedades_planta',
    'NOVEDADES DE PLANTA ELÉCTRICA',
    'mant8Group',
    'mant8',
    'Detalle',
    'textarea'
);
test_assert_same([], bit_render_schema_field_rows($plantNoField, [
    'fecha_iso' => '2026-08-05',
    'novedades_planta' => 'No',
    'mant8' => $plantPost['mant8'],
    '_conditional_default_fields' => $plantDefaultFields,
]), 'plant-generated No detail is omitted from report');

$subsectionBlockSections = [[
    'key' => 'operaciones_bloques',
    'title' => 'OPERACIONES',
    'fields' => [
        app_bitacora_subsection('bloque_sin_datos', 'OBSERVACIONES SIN DATOS', 'Esta subsección no debe aparecer.'),
        app_bitacora_yes_no_field('bloque_no', 'NOVEDAD DEL BLOQUE', 'bloqueNoGroup', 'bloque_no_detalle', 'Detalle', 'textarea', ['no_report_value' => 'Sin novedades.']),
        app_bitacora_subsection('bloque_con_datos', 'OBSERVACIONES CON DATOS', 'Esta subsección sí debe aparecer.'),
        app_bitacora_yes_no_field('planillas_bloque', 'FORMATOS DILIGENCIADOS DURANTE LA JORNADA', 'planillasBloqueGroup', 'planillas_bloque_detalle', 'Detalle', 'textarea', ['no_report_value' => 'No se diligenciaron formatos.']),
    ],
]];
$subsectionBlockHtml = bit_render_html([
    'sede' => 'PANCE',
    'fecha' => '05-08-2026',
    'fecha_iso' => '2026-08-05',
    'responsable' => 'Prueba',
    'cargo' => 'Pruebas',
    'bloque_no' => 'No',
    'planillas_bloque' => 'Si',
    'planillas_bloque_detalle' => 'check list',
], ['form_sections' => $subsectionBlockSections]);
test_assert_same(false, strpos($subsectionBlockHtml, 'OBSERVACIONES SIN DATOS') !== false, 'empty subsection is omitted');
test_assert_same(false, strpos($subsectionBlockHtml, 'Esta subsección no debe aparecer.') !== false, 'empty subsection description is omitted');
test_assert_same(true, strpos($subsectionBlockHtml, 'OBSERVACIONES CON DATOS') !== false, 'subsection with reportable data is retained');
test_assert_same(true, strpos($subsectionBlockHtml, 'Si. check list') !== false, 'subsection positive detail is retained');

$schemaOnlyHtml = bit_render_html([
    'sede' => 'PANCE',
    'fecha' => '05-08-2026',
    'responsable' => 'Prueba',
    'cargo' => 'Pruebas',
    'campo_actual' => 'Valor actual',
    'ti3' => 'Valor legado',
], [
    'form_sections' => [[
        'key' => 'actual',
        'title' => 'ACTUAL',
        'fields' => [['type' => 'text', 'name' => 'campo_actual', 'label' => 'Campo actual']],
    ]],
]);
test_assert_same(true, strpos($schemaOnlyHtml, 'Valor actual') !== false, 'schema-only report renders current field');
test_assert_same(false, strpos($schemaOnlyHtml, 'Valor legado') !== false, 'schema-only report excludes legacy field');

$yesNoDateField = app_bitacora_yes_no_field(
    'requiere_visita',
    '¿Requiere visita?',
    'requiereVisitaGroup',
    'fecha_visita',
    'Fecha de visita',
    'date',
    ['detail_default_from' => 'fechab']
);
test_assert_same('date', $yesNoDateField['detail_type'], 'yes_no date helper');
$normalizedYesNoDate = app_bitacora_normalize_dynamic_field(array_merge($yesNoDateField, ['section' => 'Operaciones']));
test_assert_same('date', $normalizedYesNoDate['detail_type'] ?? null, 'yes_no date normalization');
test_assert_same('fechab', $normalizedYesNoDate['detail_default_from'] ?? null, 'yes_no detail default source normalization');
test_assert_same('Sin novedad', $normalizedYesNoDate['no_report_value'] ?? null, 'yes_no default no report value normalization');
$invalidDefaultSourceField = app_bitacora_normalize_dynamic_field(array_merge($yesNoDateField, [
    'section' => 'Operaciones',
    'detail_default_from' => 'fecha-invalida',
]));
test_assert_same(false, isset($invalidDefaultSourceField['detail_default_from']), 'yes_no invalid detail default source removed');

ob_start();
bit_view_render_field($yesNoDateField);
$yesNoDateHtml = (string) ob_get_clean();
test_assert_same(true, strpos($yesNoDateHtml, 'type="date"') !== false, 'yes_no date rendered as date input');
test_assert_same(true, strpos($yesNoDateHtml, 'data-default-from="fechab"') !== false, 'yes_no date rendered with default source');

$yesNoDateDefinitions = bit_draft_field_definitions([['fields' => [$yesNoDateField]]]);
test_assert_same('date', $yesNoDateDefinitions['fecha_visita']['field']['type'] ?? null, 'yes_no date draft definition');

$yesNoMultiselectField = app_bitacora_yes_no_field(
    'novedad_multiselect',
    '¿Hubo novedades?',
    'novedadMultiselectGroup',
    'novedad_multiselect_detalle',
    'Tipos de novedad',
    'multiselect',
    ['detail_options' => ['Faltante', '', 'Sobrante', 'Faltante']]
);
test_assert_same(['Faltante', '', 'Sobrante', 'Faltante'], $yesNoMultiselectField['detail_options'], 'yes_no multiselect helper options');
$normalizedYesNoMultiselect = app_bitacora_normalize_dynamic_field(array_merge($yesNoMultiselectField, ['section' => 'Operaciones']));
test_assert_same('multiselect', $normalizedYesNoMultiselect['detail_type'] ?? null, 'yes_no multiselect normalization type');
test_assert_same(['Faltante', 'Sobrante'], $normalizedYesNoMultiselect['detail_options'] ?? null, 'yes_no multiselect normalization options');

ob_start();
bit_view_render_field($yesNoMultiselectField);
$yesNoMultiselectHtml = (string) ob_get_clean();
test_assert_same(true, strpos($yesNoMultiselectHtml, 'name="novedad_multiselect_detalle[]"') !== false, 'yes_no multiselect renders array name');
test_assert_same(true, strpos($yesNoMultiselectHtml, 'multiple') !== false, 'yes_no multiselect renders multiple control');
test_assert_same(true, strpos($yesNoMultiselectHtml, 'value="Faltante"') !== false, 'yes_no multiselect renders configured option');

$yesNoMultiselectDefinitions = bit_draft_field_definitions([['fields' => [$yesNoMultiselectField]]]);
test_assert_same('list', $yesNoMultiselectDefinitions['novedad_multiselect_detalle']['kind'] ?? null, 'yes_no multiselect draft definition kind');
test_assert_same(['Faltante', 'Sobrante'], $yesNoMultiselectDefinitions['novedad_multiselect_detalle']['field']['options'] ?? null, 'yes_no multiselect draft options');

$_POST = [
    'novedad_multiselect' => 'Si',
    'novedad_multiselect_detalle' => ['Faltante', 'Sobrante'],
];
test_assert_same([true, ''], bit_validate_yes_no_schema_field($yesNoMultiselectField), 'yes_no multiselect validation valid');
$_POST['novedad_multiselect_detalle'] = ['No existe'];
test_assert_same([false, 'El campo "Tipos de novedad" tiene una opción inválida.'], bit_validate_yes_no_schema_field($yesNoMultiselectField), 'yes_no multiselect validation invalid option');
$_POST['novedad_multiselect_detalle'] = [];
test_assert_same([false, 'El campo "Tipos de novedad" es obligatorio.'], bit_validate_yes_no_schema_field($yesNoMultiselectField), 'yes_no multiselect validation required');
$_POST = ['novedad_multiselect' => 'No'];
test_assert_same([true, ''], bit_validate_yes_no_schema_field($yesNoMultiselectField), 'yes_no multiselect detail omitted on No');

$yesNoMultiselectRows = bit_render_schema_field_rows($yesNoMultiselectField, [
    'fecha_iso' => '2026-08-05',
    'novedad_multiselect' => 'Si',
    'novedad_multiselect_detalle' => ['Faltante', 'Sobrante'],
]);
test_assert_same(true, strpos(implode('', $yesNoMultiselectRows), 'Si. Faltante, Sobrante') !== false, 'yes_no multiselect renders selected options in PDF');
$yesNoMultiselectMailRows = bit_section_email_rows_for_field($yesNoMultiselectField, [
    'fecha_iso' => '2026-08-05',
    'novedad_multiselect' => 'Si',
    'novedad_multiselect_detalle' => ['Faltante', 'Sobrante'],
]);
test_assert_same(true, strpos(implode('', $yesNoMultiselectMailRows), 'Si. Faltante, Sobrante') !== false, 'yes_no multiselect renders selected options in email');

test_assert_same(
    ['novedad_multiselect' => 'Si', 'novedad_multiselect_detalle' => ['Faltante', 'Sobrante']],
    bit_draft_sanitize_payload([
        'novedad_multiselect' => 'Si',
        'novedad_multiselect_detalle' => ['Faltante', 'Sobrante'],
    ], [['fields' => [$yesNoMultiselectField]]], ['sedes' => []]),
    'bit_draft accepts yes_no multiselect detail'
);
test_assert_throws(
    static fn() => bit_draft_sanitize_payload([
        'novedad_multiselect' => 'Si',
        'novedad_multiselect_detalle' => ['No existe'],
    ], [['fields' => [$yesNoMultiselectField]]], ['sedes' => []]),
    InvalidArgumentException::class,
    'bit_draft rejects invalid yes_no multiselect detail option'
);

$_POST = [
    'type' => 'yes_no',
    'name' => 'novedad_admin',
    'label' => 'Novedad administrable',
    'section' => 'Operaciones',
    'detail_name' => 'novedad_admin_detalle',
    'detail_label' => 'Tipos administrables',
    'detail_type' => 'multiselect',
    'detail_options' => "A\nB\nA",
];
$adminYesNoMultiselect = bit_admin_field_from_post(['sedes' => ['PANCE']]);
test_assert_same('yes_no', $adminYesNoMultiselect[0]['type'] ?? null, 'admin creates yes_no multiselect field');
test_assert_same('multiselect', $adminYesNoMultiselect[0]['detail_type'] ?? null, 'admin stores yes_no multiselect detail type');
test_assert_same(['A', 'B'], $adminYesNoMultiselect[0]['detail_options'] ?? null, 'admin parses yes_no multiselect detail options');
$_POST = [
    'label' => 'Novedad base',
    'col' => 'col-md-6',
    'order' => '10',
    'sedes' => ['PANCE'],
    'detail_label' => 'Tipos base',
    'detail_type' => 'multiselect',
    'detail_options' => "A\nB\nA",
];
$adminBaseYesNoMultiselect = bit_admin_base_override_from_post($yesNoMultiselectField, ['sedes' => ['PANCE']]);
test_assert_same('multiselect', $adminBaseYesNoMultiselect[0]['detail_type'] ?? null, 'admin overrides base yes_no detail type');
test_assert_same(['A', 'B'], $adminBaseYesNoMultiselect[0]['detail_options'] ?? null, 'admin parses base yes_no multiselect detail options');
$_POST = [];

$yesNoNoReportField = app_bitacora_yes_no_field(
    'novedad_configurada',
    '¿Hubo una novedad configurada?',
    'novedadConfiguradaGroup',
    'novedad_configurada_detalle',
    'Detalle',
    'textarea',
    ['no_report_value' => 'No hubo novedades configuradas']
);
test_assert_same('No hubo novedades configuradas', $yesNoNoReportField['no_report_value'] ?? null, 'yes_no custom no report value');
$normalizedYesNoNoReportField = app_bitacora_normalize_dynamic_field(array_merge($yesNoNoReportField, ['section' => 'Operaciones']));
test_assert_same('No hubo novedades configuradas', $normalizedYesNoNoReportField['no_report_value'] ?? null, 'yes_no custom no report value normalization');
$yesNoNoReportRows = bit_render_schema_field_rows($yesNoNoReportField, [
    'fecha_iso' => '2026-08-05',
    'novedad_configurada' => 'No',
]);
test_assert_same([], $yesNoNoReportRows, 'yes_no default No is omitted from report');

$yesNoDetailNoReportField = app_bitacora_yes_no_detail_group_field(
    'material_configurado',
    '¿Se entregó material configurado?',
    [app_bitacora_field('text', 'tipo_material_configurado', 'Tipo de material')],
    ['no_report_value' => 'No se entregó material configurado']
);
test_assert_same('No se entregó material configurado', $yesNoDetailNoReportField['no_report_value'] ?? null, 'yes_no detail group custom no report value');
$yesNoDetailNoReportRows = bit_render_detail_group($yesNoDetailNoReportField, [
    'fecha_iso' => '2026-08-05',
    'material_configurado' => 'No',
]);
test_assert_same([], $yesNoDetailNoReportRows, 'yes_no detail group default No is omitted from report');
$yesNoDetailDefaultRows = bit_render_detail_group(
    app_bitacora_yes_no_detail_group_field(
        'material_sin_configurar',
        '¿Se entregó otro material?',
        []
    ),
    [
        'fecha_iso' => '2026-08-05',
        'material_sin_configurar' => 'No',
    ]
);
test_assert_same([], $yesNoDetailDefaultRows, 'yes_no detail group default No is omitted from report');

$arrozBranchField = app_bitacora_yes_no_branch_group_field(
    'arroz_prueba',
    'ARROZ MEXICANO',
    [
        app_bitacora_field('number', 'porciones', 'Porciones'),
        app_bitacora_field('text', 'responsable_preparacion', 'Responsable de Preparación'),
        app_bitacora_field('text', 'reviso', 'Revisó'),
    ],
    [
        app_bitacora_field('number', 'inventario_porciones_arroz', 'Inventario de porciones de Arroz'),
        app_bitacora_field('text', 'reviso', 'Revisó'),
    ],
    ['answer_label' => 'Preparación']
);
test_assert_same(
    [
        'arroz_prueba',
        'arroz_prueba_si_porciones',
        'arroz_prueba_si_responsable_preparacion',
        'arroz_prueba_si_reviso',
        'arroz_prueba_no_inventario_porciones_arroz',
        'arroz_prueba_no_reviso',
    ],
    app_bitacora_collect_field_names([['fields' => [$arrozBranchField]]]),
    'yes_no branch group submitted names'
);
ob_start();
bit_view_render_field($arrozBranchField);
$arrozBranchFormHtml = (string) ob_get_clean();
test_assert_same(true, strpos($arrozBranchFormHtml, 'bit-special-label') !== false, 'yes_no branch group red form title');
test_assert_same(true, strpos($arrozBranchFormHtml, 'data-branch="si"') !== false && strpos($arrozBranchFormHtml, 'data-branch="no"') !== false, 'yes_no branch group renders both branches');
test_assert_same(true, strpos($arrozBranchFormHtml, 'name="arroz_prueba_si_porciones"') !== false, 'yes_no branch group renders Si number');
test_assert_same(true, strpos($arrozBranchFormHtml, 'name="arroz_prueba_no_reviso"') !== false, 'yes_no branch group isolates No repeated field');
$arrozBranchDefinitions = bit_draft_field_definitions([['fields' => [$arrozBranchField]]]);
test_assert_same('yes_no', $arrozBranchDefinitions['arroz_prueba']['field']['type'] ?? null, 'yes_no branch answer draft definition');
test_assert_same('number', $arrozBranchDefinitions['arroz_prueba_no_inventario_porciones_arroz']['field']['type'] ?? null, 'yes_no branch No number draft definition');
test_assert_same(
    [
        'arroz_prueba' => 'Si',
        'arroz_prueba_si_porciones' => '8',
        'arroz_prueba_si_responsable_preparacion' => 'Ana',
        'arroz_prueba_si_reviso' => 'Luis',
    ],
    bit_draft_sanitize_payload([
        'arroz_prueba' => 'Si',
        'arroz_prueba_si_porciones' => '8',
        'arroz_prueba_si_responsable_preparacion' => 'Ana',
        'arroz_prueba_si_reviso' => 'Luis',
    ], [['fields' => [$arrozBranchField]]], []),
    'yes_no branch Si draft payload'
);
$_POST = [
    'arroz_prueba' => 'Si',
    'arroz_prueba_si_porciones' => '8',
    'arroz_prueba_si_responsable_preparacion' => 'Ana',
    'arroz_prueba_si_reviso' => 'Luis',
];
test_assert_same([true, ''], bit_validate_branch_groups([$arrozBranchField], '2026-08-05'), 'yes_no branch Si validation');
$_POST = [
    'arroz_prueba' => 'No',
    'arroz_prueba_no_inventario_porciones_arroz' => '3',
    'arroz_prueba_no_reviso' => 'Luis',
];
test_assert_same([true, ''], bit_validate_branch_groups([$arrozBranchField], '2026-08-05'), 'yes_no branch No validation');
unset($_POST['arroz_prueba_no_reviso']);
test_assert_same([false, 'El campo "ARROZ MEXICANO - Revisó" es obligatorio.'], bit_validate_branch_groups([$arrozBranchField], '2026-08-05'), 'yes_no branch validates active No fields');
$arrozYesRows = bit_render_schema_field_rows($arrozBranchField, [
    'fecha_iso' => '2026-08-05',
    'arroz_prueba' => 'Si',
    'arroz_prueba_si_porciones' => '8',
    'arroz_prueba_si_responsable_preparacion' => 'Ana',
    'arroz_prueba_si_reviso' => 'Luis',
]);
$arrozYesHtml = implode('', $arrozYesRows);
test_assert_same(true, strpos($arrozYesHtml, 'style="color:#d71920;"') !== false, 'yes_no branch report title is red');
test_assert_same(true, strpos($arrozYesHtml, 'style="color:#000;">Preparación:</strong>') !== false && strpos($arrozYesHtml, 'style="color:#000;">Si</span>') !== false, 'yes_no branch Si answer label is black');
test_assert_same(true, strpos($arrozYesHtml, 'Porciones') !== false && strpos($arrozYesHtml, 'Ana') !== false, 'yes_no branch Si report fields');
$arrozNoRows = bit_render_schema_field_rows($arrozBranchField, [
    'fecha_iso' => '2026-08-05',
    'arroz_prueba' => 'No',
    'arroz_prueba_no_inventario_porciones_arroz' => '3',
    'arroz_prueba_no_reviso' => 'Luis',
]);
$arrozNoHtml = implode('', $arrozNoRows);
test_assert_same(true, strpos($arrozNoHtml, 'style="color:#d71920;">ARROZ MEXICANO</strong>') !== false, 'yes_no branch No keeps title red');
test_assert_same(true, strpos($arrozNoHtml, 'style="color:#000;">Preparación:</strong>') !== false && strpos($arrozNoHtml, 'style="color:#000;">No</span>') !== false, 'yes_no branch No answer label is black');
test_assert_same(false, strpos($arrozNoHtml, 'Sin novedad') !== false, 'yes_no branch No has no default response');
test_assert_same(true, strpos($arrozNoHtml, 'Inventario de porciones de Arroz') !== false, 'yes_no branch No report fields');
$arrozNoEmailHtml = implode('', bit_section_email_rows_for_field($arrozBranchField, [
    'fecha_iso' => '2026-08-05',
    'arroz_prueba' => 'No',
    'arroz_prueba_no_inventario_porciones_arroz' => '3',
    'arroz_prueba_no_reviso' => 'Luis',
]));
test_assert_same(true, strpos($arrozNoEmailHtml, 'style="color:#d71920;">ARROZ MEXICANO</strong>') !== false, 'yes_no branch email title is red');
test_assert_same(true, strpos($arrozNoEmailHtml, 'style="color:#000;">Preparación:</strong>') !== false && strpos($arrozNoEmailHtml, 'style="color:#000;">No</span>') !== false, 'yes_no branch email answer label is black');
$_POST = [];

$yesNoNumericSuffixField = app_bitacora_yes_no_field(
    'hielo_kolbitos_prueba',
    '¿Se compra hielo a Kolbitos?',
    'hieloKolbitosPruebaGroup',
    'hielo_kolbitos_cantidad',
    'Cantidad comprada',
    'number',
    [
        'suffix_singular' => 'bolsa',
        'suffix_plural' => 'bolsas',
        'no_report_value' => 'No se realizó compra de hielo.',
    ]
);
$yesNoNumericNoRows = bit_render_schema_field_rows($yesNoNumericSuffixField, [
    'fecha_iso' => '2026-08-05',
    'hielo_kolbitos_prueba' => 'No',
]);
test_assert_same([], $yesNoNumericNoRows, 'yes_no numeric default No is omitted from report');
$yesNoNumericOneRows = bit_render_schema_field_rows($yesNoNumericSuffixField, [
    'fecha_iso' => '2026-08-05',
    'hielo_kolbitos_prueba' => 'Si',
    'hielo_kolbitos_cantidad' => '1',
]);
test_assert_same(true, strpos(implode('', $yesNoNumericOneRows), '1 bolsa') !== false, 'yes_no numeric singular suffix');
$yesNoNumericManyRows = bit_render_schema_field_rows($yesNoNumericSuffixField, [
    'fecha_iso' => '2026-08-05',
    'hielo_kolbitos_prueba' => 'Si',
    'hielo_kolbitos_cantidad' => '3',
]);
test_assert_same(true, strpos(implode('', $yesNoNumericManyRows), '3 bolsas') !== false, 'yes_no numeric plural suffix');

$currencyField = app_bitacora_field('number', 'valor_configurado', 'Valor configurado', [
    'number_format' => 'currency',
    'number_decimals' => 0,
]);
$currencyRows = bit_render_schema_field_rows($currencyField, [
    'fecha_iso' => '2026-08-05',
    'valor_configurado' => '123456',
]);
test_assert_same(true, strpos(implode('', $currencyRows), '$123.456') !== false, 'number currency rendered in PDF');

$formattedGroupField = app_bitacora_yes_no_quantity_group_field(
    'registros_formato',
    '¿Hubo registros con formato?',
    'registros_formato_cantidad',
    'Cantidad de registros',
    [
        app_bitacora_field('number', 'valor', 'Valor', ['number_format' => 'currency']),
        app_bitacora_field('number', 'unidades', 'Unidades', ['suffix' => 'unidades']),
    ]
);
$formattedGroupItem = bit_render_group_item($formattedGroupField, 1, [
    'registros_formato_1_valor' => '123456',
    'registros_formato_1_unidades' => '3',
]);
test_assert_same(true, strpos($formattedGroupItem, '$123.456') !== false, 'number currency rendered in group item');
test_assert_same(true, strpos($formattedGroupItem, '3 unidades') !== false, 'number suffix rendered in group item');

$normalizedGroupField = app_bitacora_normalize_dynamic_field([
    'type' => 'yes_no_quantity_group',
    'name' => 'grupo_formato',
    'label' => 'Grupo con formato',
    'fields' => [[
        'type' => 'number',
        'name' => 'valor',
        'label' => 'Valor',
        'number_format' => 'currency',
        'number_decimals' => 2,
        'suffix' => 'COP',
    ]],
]);
test_assert_same('currency', $normalizedGroupField['fields'][0]['number_format'] ?? null, 'group number format normalization');
test_assert_same(2, $normalizedGroupField['fields'][0]['number_decimals'] ?? null, 'group number decimals normalization');
test_assert_same('COP', $normalizedGroupField['fields'][0]['suffix'] ?? null, 'group number suffix normalization');

$multiselectField = app_bitacora_normalize_dynamic_field([
    'type' => 'multiselect',
    'name' => 'categorias',
    'label' => 'Categorías',
    'required' => true,
    'options' => ['A', '', 'B', 'A'],
]);
test_assert_same('multiselect', $multiselectField['type'] ?? null, 'multiselect dynamic type normalization');
test_assert_same(['A', 'B', 'A'], $multiselectField['options'] ?? null, 'multiselect dynamic options normalization');

ob_start();
bit_view_multiselect_field($multiselectField);
$multiselectHtml = (string) ob_get_clean();
test_assert_same(true, strpos($multiselectHtml, 'name="categorias[]"') !== false, 'multiselect renders array name');
test_assert_same(true, strpos($multiselectHtml, ' multiple') !== false, 'multiselect renders multiple control');
test_assert_same(true, strpos($multiselectHtml, ' required') !== false, 'multiselect renders required control');

$_POST = [
    'type' => 'multiselect',
    'name' => 'categorias',
    'label' => 'Categorías',
    'section' => 'Operaciones',
    'options' => "A\nB\nA",
    'required' => '1',
];
$adminMultiselect = bit_admin_field_from_post(['sedes' => ['PANCE']]);
test_assert_same('multiselect', ($adminMultiselect[0]['type'] ?? null), 'admin creates multiselect field');
test_assert_same(['A', 'B'], ($adminMultiselect[0]['options'] ?? null), 'admin parses multiselect options');
$_POST = [];

$multiselectRows = bit_render_schema_field_rows($multiselectField, [
    'fecha_iso' => '2026-08-05',
    'categorias' => ['A', 'B'],
]);
test_assert_same(true, strpos(implode('', $multiselectRows), 'A, B') !== false, 'multiselect renders selected options in report');

$yesNoSedeField = app_bitacora_yes_no_field(
    'novedad_sede',
    '¿Hubo novedad?',
    'novedadSedeGroup',
    'novedad_sede_detalle',
    'Detalle',
    'textarea',
    ['sedes' => ['PANCE']]
);
test_assert_same(['PANCE'], $yesNoSedeField['sedes'] ?? null, 'yes_no extra configuration');

$companyOneConfig = app_bitacora_configs()[1];
$companyOneSections = app_bitacora_default_form_sections($companyOneConfig, 1);
$companyOneSectionsByKey = [];
foreach ($companyOneSections as $section) {
    $companyOneSectionsByKey[(string) ($section['key'] ?? '')] = $section;
}
test_assert_same(false, isset($companyOneSectionsByKey['chetano']), 'Chetano is not an independent section');
test_assert_same(
    'fechab',
    $companyOneSectionsByKey['descanso_coordinador']['fields'][0]['detail_default_from'] ?? null,
    'coordinator rest defaults from report date'
);

$operationsFieldsByName = [];
foreach ((array) ($companyOneSectionsByKey['operaciones']['fields'] ?? []) as $field) {
    $operationsFieldsByName[(string) ($field['name'] ?? '')] = $field;
}
$operationsFields = array_values((array) ($companyOneSectionsByKey['operaciones']['fields'] ?? []));
$barSubsectionIndex = null;
$chetanoSubsectionIndex = null;
$reservationsSubsectionIndex = null;
$lastChetanoFieldIndex = null;
foreach ($operationsFields as $index => $field) {
    $fieldName = (string) ($field['name'] ?? '');
    $fieldType = (string) ($field['type'] ?? '');
    if ($fieldName === 'novedades_jefe_bar' && $fieldType === 'subsection') {
        $barSubsectionIndex = $index;
    } elseif ($fieldName === 'novedades_chetano' && $fieldType === 'subsection') {
        $chetanoSubsectionIndex = $index;
    } elseif ($fieldName === 'novedades_reservas' && $fieldType === 'subsection') {
        $reservationsSubsectionIndex = $index;
    } elseif ($fieldName === 'mp_chetano') {
        $lastChetanoFieldIndex = $index;
    }
}
test_assert_same(
    true,
    $barSubsectionIndex !== null
        && $chetanoSubsectionIndex > $barSubsectionIndex
        && $lastChetanoFieldIndex > $chetanoSubsectionIndex
        && $reservationsSubsectionIndex > $lastChetanoFieldIndex,
    'Chetano block ordered between bar and reservations'
);
foreach (['novedades_chetano', 'chetano_novedades', 'formatos_chetano', 'ventas_chetano', 'dom_chetano', 'mp_chetano'] as $chetanoFieldName) {
    test_assert_same(true, isset($operationsFieldsByName[$chetanoFieldName]), 'Chetano field in operations: ' . $chetanoFieldName);
    test_assert_same(['PANCE', 'UNICENTRO'], $operationsFieldsByName[$chetanoFieldName]['sedes'] ?? null, 'Chetano field sedes: ' . $chetanoFieldName);
}
test_assert_same('subsection', $operationsFieldsByName['novedades_chetano']['type'] ?? null, 'Chetano subsection type');
$panceFieldNames = app_bitacora_collect_field_names($companyOneSections, 'PANCE');
$unicentroFieldNames = app_bitacora_collect_field_names($companyOneSections, 'UNICENTRO');
$ciudadJardinFieldNames = app_bitacora_collect_field_names($companyOneSections, 'CIUDAD JARDÍN');
test_assert_same(true, in_array('formatos_chetano', $panceFieldNames, true), 'Chetano visible in PANCE');
test_assert_same(true, in_array('formatos_chetano', $unicentroFieldNames, true), 'Chetano visible in UNICENTRO');
test_assert_same(false, in_array('formatos_chetano', $ciudadJardinFieldNames, true), 'Chetano hidden outside configured sedes');
$companyOneDraftDefinitions = bit_draft_field_definitions($companyOneSections, 'PANCE');
test_assert_same(true, count($companyOneDraftDefinitions) > 0, 'full PANCE schema supports drafts');
$configuredCompanyOneSections = app_bitacora_apply_config_json($companyOneSections, ['dynamic_fields' => []]);
test_assert_same(
    true,
    count(bit_draft_field_definitions($configuredCompanyOneSections, 'PANCE')) > 0,
    'configured and ordered PANCE schema supports drafts'
);
$reservationsField = $operationsFieldsByName['reservas'];
test_assert_same('yes_no_quantity_group', $reservationsField['type'] ?? null, 'reservations quantity group');
test_assert_same('yes_no', $companyOneDraftDefinitions['reservas_1_decoracion_reserva']['field']['type'] ?? null, 'nested simple radio draft definition');

ob_start();
bit_view_render_field($reservationsField);
$reservationsHtml = (string) ob_get_clean();
test_assert_same(true, strpos($reservationsHtml, 'name="reservas_1_decoracion_reserva"') !== false, 'nested simple radio rendered name');
test_assert_same(true, strpos($reservationsHtml, 'value="Si"') !== false && strpos($reservationsHtml, 'value="No"') !== false, 'nested simple radio rendered options');

$_POST = ['decoracion' => 'Si'];
test_assert_same([true, ''], bit_validate_configured_value(['type' => 'simple_radio', 'required' => true], 'decoracion', 'Decoración'), 'nested simple radio valid');
$_POST = ['decoracion' => 'Tal vez'];
test_assert_same([false, 'El campo "Decoración" debe ser Si o No.'], bit_validate_configured_value(['type' => 'simple_radio', 'required' => true], 'decoracion', 'Decoración'), 'nested simple radio invalid');

$draftRadioPayload = bit_draft_sanitize_payload([
    'sede' => 'PANCE',
    'reservas_1_decoracion_reserva' => 'Si',
], $companyOneSections, $companyOneConfig);
test_assert_same('Si', $draftRadioPayload['reservas_1_decoracion_reserva'] ?? null, 'nested simple radio draft payload');
$reservationReportItem = bit_render_group_item($reservationsField, 1, ['reservas_1_decoracion_reserva' => 'Si']);
test_assert_same(true, strpos($reservationReportItem, 'DECORACIÓN') !== false && strpos($reservationReportItem, 'Si') !== false, 'nested simple radio report');
$reservationNoReportItem = bit_render_group_item($reservationsField, 1, ['reservas_1_decoracion_reserva' => 'No']);
test_assert_same('', $reservationNoReportItem, 'nested exact No is omitted from report');

$quantityNoReportField = app_bitacora_yes_no_quantity_group_field(
    'visitas_prueba',
    '¿Hubo visitas?',
    'visitas_prueba_cantidad',
    'Cantidad de visitas',
    [['type' => 'text', 'name' => 'visitante', 'label' => 'Visitante']],
    ['no_report_value' => 'No se recibieron visitas']
);
$normalizedQuantityNoReportField = app_bitacora_normalize_dynamic_field($quantityNoReportField);
test_assert_same('No se recibieron visitas', $normalizedQuantityNoReportField['no_report_value'] ?? null, 'quantity group no report value normalization');
$quantityNoRows = bit_render_quantity_group($quantityNoReportField, [
    'fecha_iso' => '2026-08-05',
    'visitas_prueba' => 'No',
]);
test_assert_same([], $quantityNoRows, 'quantity group default No is omitted from report');
$quantityDefaultNoRows = bit_render_quantity_group(array_diff_key($quantityNoReportField, ['no_report_value' => true]), [
    'fecha_iso' => '2026-08-05',
    'visitas_prueba' => 'No',
]);
test_assert_same([], $quantityDefaultNoRows, 'quantity group default No is omitted from report');

$quantitySuffixField = app_bitacora_yes_no_quantity_group_field(
    'visitas_con_unidades',
    '¿Hubo visitas con unidades?',
    'visitas_con_unidades_cantidad',
    'Cantidad de visitas',
    [['type' => 'text', 'name' => 'visitante', 'label' => 'Visitante']],
    ['suffix' => 'unidades', 'suffix_singular' => 'unidad', 'suffix_plural' => 'unidades']
);
$normalizedQuantitySuffixField = app_bitacora_normalize_dynamic_field($quantitySuffixField);
test_assert_same('unidades', $normalizedQuantitySuffixField['suffix'] ?? null, 'quantity group suffix normalization');
test_assert_same('unidad', $normalizedQuantitySuffixField['suffix_singular'] ?? null, 'quantity group singular suffix normalization');
test_assert_same('unidades', $normalizedQuantitySuffixField['suffix_plural'] ?? null, 'quantity group plural suffix normalization');
$quantitySuffixRows = bit_render_quantity_group($quantitySuffixField, [
    'fecha_iso' => '2026-08-05',
    'visitas_con_unidades' => 'Si',
    'visitas_con_unidades_cantidad' => '3',
]);
test_assert_same(true, strpos(implode('', $quantitySuffixRows), '3 unidades') !== false, 'quantity group suffix rendering');
$quantitySingularRows = bit_render_quantity_group($quantitySuffixField, [
    'fecha_iso' => '2026-08-05',
    'visitas_con_unidades' => 'Si',
    'visitas_con_unidades_cantidad' => '1',
]);
test_assert_same(true, strpos(implode('', $quantitySingularRows), '1 unidad') !== false, 'quantity group singular suffix rendering');

$directQuantityField = app_bitacora_quantity_group_field(
    'incidentes_prueba',
    'Incidentes',
    'incidentes_prueba_cantidad',
    'Cantidad de incidentes',
    [
        ['type' => 'text', 'name' => 'descripcion', 'label' => 'Descripción', 'required' => true],
        ['type' => 'simple_radio', 'name' => 'resuelto', 'label' => '¿Resuelto?', 'required' => true],
    ],
    ['max' => 2, 'zero_report_value' => 'No se presentaron incidentes']
);
$directQuantityField = app_bitacora_normalize_dynamic_field($directQuantityField);
test_assert_same('quantity_group', $directQuantityField['type'] ?? null, 'direct quantity group normalization type');
test_assert_same(true, $directQuantityField['required'] ?? null, 'direct quantity group is required');
test_assert_same(0, $directQuantityField['min'] ?? null, 'direct quantity group minimum is zero');
test_assert_same('No se presentaron incidentes', $directQuantityField['zero_report_value'] ?? null, 'direct quantity group zero report value');
test_assert_same(true, app_bitacora_normalize_field_override(['required' => false], $directQuantityField)['required'] ?? null, 'direct quantity group override remains required');
$directQuantitySections = [['key' => 'prueba', 'fields' => [$directQuantityField]]];
test_assert_same(
    ['incidentes_prueba_cantidad', 'incidentes_prueba_1_descripcion', 'incidentes_prueba_1_resuelto', 'incidentes_prueba_2_descripcion', 'incidentes_prueba_2_resuelto'],
    app_bitacora_collect_field_names($directQuantitySections),
    'direct quantity group submitted names exclude technical prefix'
);
ob_start();
bit_view_render_field($directQuantityField);
$directQuantityHtml = (string) ob_get_clean();
test_assert_same(0, preg_match('/\sname="incidentes_prueba"/', $directQuantityHtml), 'direct quantity group has no answer input');
test_assert_same(true, strpos($directQuantityHtml, 'name="incidentes_prueba_cantidad"') !== false && strpos($directQuantityHtml, 'min="0"') !== false, 'direct quantity group renders required zero-based quantity');
$directDraftDefinitions = bit_draft_field_definitions($directQuantitySections);
test_assert_same(false, isset($directDraftDefinitions['incidentes_prueba']), 'direct quantity draft excludes technical prefix');
test_assert_same(0, $directDraftDefinitions['incidentes_prueba_cantidad']['field']['min'] ?? null, 'direct quantity draft allows zero');
test_assert_same(
    ['incidentes_prueba_cantidad' => '0'],
    bit_draft_sanitize_payload(['incidentes_prueba_cantidad' => '0'], $directQuantitySections, []),
    'direct quantity draft accepts zero'
);
$_POST = ['incidentes_prueba_cantidad' => '0'];
test_assert_same([true, ''], bit_validate_direct_quantity_groups([$directQuantityField], '2026-08-05'), 'direct quantity validation accepts zero');
$_POST = ['incidentes_prueba_cantidad' => '1', 'incidentes_prueba_1_descripcion' => 'Caída', 'incidentes_prueba_1_resuelto' => 'Si'];
test_assert_same([true, ''], bit_validate_direct_quantity_groups([$directQuantityField], '2026-08-05'), 'direct quantity validation accepts complete row');
$_POST = ['incidentes_prueba_cantidad' => '1'];
test_assert_same(false, bit_validate_direct_quantity_groups([$directQuantityField], '2026-08-05')[0], 'direct quantity validation requires visible row fields');
$directZeroRows = bit_render_direct_quantity_group($directQuantityField, ['fecha_iso' => '2026-08-05', 'incidentes_prueba_cantidad' => '0']);
test_assert_same([], $directZeroRows, 'direct quantity zero default is omitted from report');
$directPositiveRows = bit_render_direct_quantity_group($directQuantityField, [
    'fecha_iso' => '2026-08-05',
    'incidentes_prueba_cantidad' => '1',
    'incidentes_prueba_1_descripcion' => 'Caída',
    'incidentes_prueba_1_resuelto' => 'Si',
]);
test_assert_same(true, strpos(implode('', $directPositiveRows), 'Caída') !== false && strpos(implode('', $directPositiveRows), 'Si') !== false, 'direct quantity report renders rows');

$draftKey = random_bytes(32);
$draftAad = bit_draft_aad(7, 1, 'operational', str_repeat('a', 64), str_repeat('b', 64));
$draftEncrypted = bit_draft_encrypt('{"sede":"PANCE"}', $draftAad, $draftKey);
test_assert_same(
    '{"sede":"PANCE"}',
    bit_draft_decrypt($draftEncrypted['ciphertext'], $draftEncrypted['iv'], $draftEncrypted['tag'], $draftAad, $draftKey),
    'bit_draft encryption round trip'
);
test_assert_throws(static function () use ($draftEncrypted, $draftAad, $draftKey): void {
    $tampered = $draftEncrypted['ciphertext'];
    $tampered[0] = chr(ord($tampered[0]) ^ 1);
    bit_draft_decrypt($tampered, $draftEncrypted['iv'], $draftEncrypted['tag'], $draftAad, $draftKey);
}, RuntimeException::class, 'bit_draft rejects tampered ciphertext');
test_assert_throws(static function () use ($draftEncrypted, $draftKey): void {
    bit_draft_decrypt($draftEncrypted['ciphertext'], $draftEncrypted['iv'], $draftEncrypted['tag'], 'different-aad', $draftKey);
}, RuntimeException::class, 'bit_draft rejects changed metadata');
test_assert_same($draftKey, bit_draft_key(base64_encode($draftKey)), 'bit_draft accepts canonical key');
test_assert_throws(static fn() => bit_draft_key(base64_encode(random_bytes(31))), RuntimeException::class, 'bit_draft rejects short key');
$draftKeyV2 = random_bytes(32);
$parsedKeyring = bit_draft_parse_keyring(base64_encode($draftKey), json_encode(['1' => base64_encode($draftKey), '2' => base64_encode($draftKeyV2)], JSON_THROW_ON_ERROR));
test_assert_same($draftKey, $parsedKeyring[1], 'bit_draft keyring keeps legacy version');
test_assert_same($draftKeyV2, $parsedKeyring[2], 'bit_draft keyring accepts next version');
test_assert_throws(
    static fn() => bit_draft_parse_keyring(base64_encode($draftKey), json_encode(['1' => base64_encode($draftKeyV2)], JSON_THROW_ON_ERROR)),
    RuntimeException::class,
    'bit_draft keyring rejects conflicting legacy key'
);
test_assert_same(false, bit_draft_aad(7, 1, 'operational', str_repeat('a', 64), str_repeat('b', 64), 1) === bit_draft_aad(7, 1, 'operational', str_repeat('a', 64), str_repeat('b', 64), 2), 'bit_draft AAD binds key version');

$hashSchemaA = [['key' => 'base', 'fields' => [['name' => 'x', 'type' => 'text']]]];
$hashSchemaB = [['fields' => [['type' => 'text', 'name' => 'x']], 'key' => 'base']];
test_assert_same(bit_draft_schema_hash($hashSchemaA), bit_draft_schema_hash($hashSchemaB), 'bit_draft schema hash canonical');
test_assert_same(false, bit_draft_schema_hash($hashSchemaA) === bit_draft_schema_hash([['key' => 'base', 'fields' => [['name' => 'y', 'type' => 'text']]]]), 'bit_draft schema hash changes');

$draftSections = [[
    'key' => 'base',
    'fields' => [
        ['type' => 'select', 'name' => 'sede', 'options' => ['PANCE', 'FLORA']],
        ['type' => 'text', 'name' => 'nombre', 'max_length' => 10],
        ['type' => 'select', 'name' => 'estado', 'options' => ['A', 'B']],
        ['type' => 'yes_no', 'name' => 'novedad', 'detail_name' => 'novedad_detalle'],
        [
            'type' => 'yes_no_quantity_group',
            'name' => 'personas',
            'quantity_name' => 'personas_cantidad',
            'min' => 1,
            'max' => 2,
            'fields' => [['type' => 'text', 'name' => 'persona']],
        ],
        ['type' => 'text', 'name' => 'solo_flora', 'sedes' => ['FLORA']],
        ['type' => 'multiselect', 'name' => 'categorias', 'options' => ['A', 'B']],
        ['type' => 'multiselect_detail_group', 'name' => 'visitas', 'options' => ['SST']],
    ],
]];
$draftCompany = ['sedes' => ['PANCE', 'FLORA']];
test_assert_same(
    ['sede' => 'PANCE', 'nombre' => 'Ana', 'novedad' => 'Si', 'personas' => 'Si', 'personas_cantidad' => '1'],
    bit_draft_sanitize_payload([
        'sede' => 'PANCE',
        'nombre' => 'Ana',
        'novedad' => 'Si',
        'personas' => 'Si',
        'personas_cantidad' => '1',
    ], $draftSections, $draftCompany),
    'bit_draft accepts structurally valid incomplete group'
);
test_assert_same(
    ['categorias' => ['A', 'B']],
    bit_draft_sanitize_payload(['categorias' => ['A', 'B']], $draftSections, $draftCompany),
    'bit_draft accepts multiselect options'
);
test_assert_throws(
    static fn() => bit_draft_sanitize_payload(['categorias' => ['C']], $draftSections, $draftCompany),
    InvalidArgumentException::class,
    'bit_draft rejects invalid multiselect option'
);
test_assert_throws(
    static fn() => bit_draft_sanitize_payload(['sede' => 'PANCE', 'metadata' => 'x'], $draftSections, $draftCompany),
    InvalidArgumentException::class,
    'bit_draft rejects unknown metadata'
);
test_assert_throws(
    static fn() => bit_draft_sanitize_payload(['sede' => 'PANCE', 'estado' => 'C'], $draftSections, $draftCompany),
    InvalidArgumentException::class,
    'bit_draft rejects invalid option'
);
test_assert_throws(
    static fn() => bit_draft_sanitize_payload(['sede' => 'PANCE', 'solo_flora' => 'x'], $draftSections, $draftCompany),
    InvalidArgumentException::class,
    'bit_draft enforces field sede'
);
test_assert_throws(
    static fn() => bit_draft_sanitize_payload(['sede' => 'DESCONOCIDA'], $draftSections, $draftCompany),
    InvalidArgumentException::class,
    'bit_draft rejects invalid sede'
);
test_assert_throws(
    static fn() => bit_draft_sanitize_payload(['nombre' => '12345678901'], $draftSections, $draftCompany),
    InvalidArgumentException::class,
    'bit_draft enforces length'
);
[$compatiblePayload, $omittedDraftFields] = bit_draft_sanitize_payload_compatible(
    ['sede' => 'PANCE', 'nombre' => 'Ana', 'campo_eliminado' => 'dato'],
    $draftSections,
    $draftCompany
);
test_assert_same(['sede' => 'PANCE', 'nombre' => 'Ana'], $compatiblePayload, 'bit_draft compatible payload');
test_assert_same(['campo_eliminado'], $omittedDraftFields, 'bit_draft compatible omitted fields');
[$compatibleVisitorPayload, $omittedVisitorFields] = bit_draft_sanitize_payload_compatible([
    'visitas_detalles' => ['SST' => ['visitante' => 'Ana - SST', 'hora_inicio' => '08:00', 'hora_final' => '09:00', 'actividades' => 'Revisión']],
    'visitas' => ['Ana - SST'],
], $draftSections, $draftCompany);
test_assert_same(['SST' => ['visitante' => 'Ana - SST', 'hora_inicio' => '08:00', 'hora_final' => '09:00', 'actividades' => 'Revisión']], $compatibleVisitorPayload['visitas_detalles'], 'bit_draft compatible visitor details before selection');
test_assert_same([], $omittedVisitorFields, 'bit_draft compatible visitor ordering has no omissions');
test_assert_same('2026-08-03T12:30:00Z', bit_draft_iso_utc('2026-08-03 12:30:00'), 'bit_draft UTC ISO date');

$_POST = ['campo_texto' => 'abc'];
test_assert_same([true, ''], bit_validate_configured_value(['type' => 'text', 'required' => true], 'campo_texto', 'Campo texto'), 'bit_validate_configured_value text valid');

$_POST = ['cantidad' => '11'];
test_assert_same([false, 'El campo "Cantidad" debe ser menor o igual a 10.'], bit_validate_configured_value(['type' => 'number', 'max' => 10], 'cantidad', 'Cantidad'), 'bit_validate_configured_value number max');

$_POST = ['opcion' => 'C'];
test_assert_same([false, 'El campo "Opción" tiene una opción inválida.'], bit_validate_configured_value(['type' => 'select', 'options' => ['A', 'B']], 'opcion', 'Opción'), 'bit_validate_configured_value select invalid');
$_POST = ['categorias' => ['A', 'B']];
test_assert_same([true, ''], bit_validate_multiselect_schema_field(['name' => 'categorias', 'label' => 'Categorías', 'required' => true, 'options' => ['A', 'B']]), 'multiselect validation valid');
$_POST = ['categorias' => ['C']];
test_assert_same([false, 'El campo "Categorías" tiene una opción inválida.'], bit_validate_multiselect_schema_field(['name' => 'categorias', 'label' => 'Categorías', 'required' => true, 'options' => ['A', 'B']]), 'multiselect validation invalid option');
test_assert_same(true, bit_valid_date_value('2026-08-03'), 'bit_valid_date_value valid');
test_assert_same(false, bit_valid_date_value('2026-02-30'), 'bit_valid_date_value impossible date');
test_assert_same(true, bit_valid_time_value('23:59'), 'bit_valid_time_value valid');
test_assert_same(false, bit_valid_time_value('99:99'), 'bit_valid_time_value invalid');

$_POST = ['requiere_visita' => 'Si', 'fecha_visita' => '2026-08-05'];
test_assert_same([true, ''], bit_validate_yes_no_schema_field($yesNoDateField), 'yes_no date valid');
$_POST = ['requiere_visita' => 'Si', 'fecha_visita' => '2026-02-30'];
test_assert_same([false, 'El campo "Fecha de visita" debe ser una fecha válida.'], bit_validate_yes_no_schema_field($yesNoDateField), 'yes_no date invalid');
$_POST = ['requiere_visita' => 'No'];
test_assert_same([true, ''], bit_validate_yes_no_schema_field($yesNoDateField), 'yes_no date omitted when no');

test_assert_same([true, ''], bit_validate_sede(['sedes' => ['PANCE']], 'PANCE'), 'bit_validate_sede valid');
test_assert_same([false, 'La sede seleccionada no pertenece a la empresa activa.'], bit_validate_sede(['sedes' => ['PANCE']], 'FLORA'), 'bit_validate_sede invalid');
test_assert_same(true, bit_valid_area_visit_label('Juan Perez - Coordinador'), 'bit_valid_area_visit_label valid');
test_assert_same(false, bit_valid_area_visit_label('Juan - Coordinador'), 'bit_valid_area_visit_label invalid');

$_POST = [
    'visitas' => ['Juan Perez - Coordinador'],
    'visitas_detalles' => [
        'v_1' => [
            'visitante' => 'Juan Perez - Coordinador',
            'hora_inicio' => '08:00',
            'hora_final' => '09:00',
            'actividades' => 'Revision',
        ],
    ],
];
test_assert_same([true, ''], bit_validate_multiselect_detail_groups([['name' => 'visitas', 'label' => 'Visitas', 'required' => true]]), 'bit_validate_multiselect_detail_groups valid');

$_ENV['SESSION_SAMESITE'] = 'None';
putenv('SESSION_SAMESITE=None');
test_assert_same('Lax', app_session_same_site(false), 'app_session_same_site none without secure');
test_assert_same('None', app_session_same_site(true), 'app_session_same_site none secure');

$_ENV['SESSION_IDLE_TIMEOUT_SECONDS'] = '900';
putenv('SESSION_IDLE_TIMEOUT_SECONDS=900');
test_assert_same(900, app_session_timeout_seconds('SESSION_IDLE_TIMEOUT_SECONDS'), 'app_session_timeout_seconds valid');

$_ENV['SESSION_MAX_LIFETIME_SECONDS'] = 'invalid';
putenv('SESSION_MAX_LIFETIME_SECONDS=invalid');
test_assert_same(0, app_session_timeout_seconds('SESSION_MAX_LIFETIME_SECONDS'), 'app_session_timeout_seconds invalid');

$_ENV['SESSION_IDLE_TIMEOUT_SECONDS'] = '3600';
putenv('SESSION_IDLE_TIMEOUT_SECONDS=3600');
$_ENV['SESSION_MAX_LIFETIME_SECONDS'] = '43200';
putenv('SESSION_MAX_LIFETIME_SECONDS=43200');
$previousSession = $_SESSION ?? [];
$_SESSION = [
    's_usuario' => 'test-user',
    's_session_created_at' => 1000,
    's_last_activity_at' => 2000,
];
$sessionExpiration = app_session_expiration_data(5000);
test_assert_same(5600, $sessionExpiration['idle_expires_at'], 'app_session_expiration_data idle deadline');
test_assert_same(44200, $sessionExpiration['max_expires_at'], 'app_session_expiration_data max deadline');
test_assert_same(5600, $sessionExpiration['expires_at'], 'app_session_expiration_data earliest deadline');
test_assert_same(600, app_session_warning_seconds(), 'app_session_warning_seconds');
$_SESSION = $previousSession;

foreach ([
    'SMTP_HOST' => 'mailpit',
    'SMTP_PORT' => '1025',
    'SMTP_SECURE' => 'none',
    'SMTP_AUTH' => 'false',
    'SMTP_FROM' => 'bitacora@localhost.test',
    'SMTP_TIMEOUT_SECONDS' => '7',
] as $key => $value) {
    $_ENV[$key] = $value;
    putenv($key . '=' . $value);
}
$testMailer = new PHPMailer\PHPMailer\PHPMailer(true);
app_configure_mailer($testMailer);
test_assert_same(false, $testMailer->SMTPAuth, 'app_configure_mailer without auth');
test_assert_same(1025, $testMailer->Port, 'app_configure_mailer port');
test_assert_same(7, $testMailer->Timeout, 'app_configure_mailer timeout');

$_ENV['APP_ENV'] = 'production';
putenv('APP_ENV=production');
$productionInsecureRejected = false;
try {
    app_configure_mailer(new PHPMailer\PHPMailer\PHPMailer(true));
} catch (RuntimeException $e) {
    $productionInsecureRejected = true;
}
test_assert_same(true, $productionInsecureRejected, 'app_configure_mailer rejects insecure production mode');
$_ENV['APP_ENV'] = 'development';
putenv('APP_ENV=development');

$_ENV['SMTP_SECURE'] = 'invalid';
putenv('SMTP_SECURE=invalid');
$invalidSecureRejected = false;
try {
    app_configure_mailer(new PHPMailer\PHPMailer\PHPMailer(true));
} catch (RuntimeException $e) {
    $invalidSecureRejected = true;
}
test_assert_same(true, $invalidSecureRejected, 'app_configure_mailer rejects invalid secure mode');
$_ENV['SMTP_SECURE'] = 'none';
putenv('SMTP_SECURE=none');

test_assert_same(['A', 'B'], bit_admin_parse_lines(" A\nB\nA "), 'bit_admin_parse_lines');
test_assert_same(['a' => 'A', 0 => 'B'], bit_admin_parse_option_lines("a|A\nB"), 'bit_admin_parse_option_lines');
test_assert_same(
    ['campo', 'campo_detalle', 'campo_cantidad', 'grupo_campo'],
    bit_admin_field_identifiers([
        'name' => 'campo',
        'detail_name' => 'campo_detalle',
        'quantity_name' => 'campo_cantidad',
        'id' => 'campo',
        'group_id' => 'grupo_campo',
    ]),
    'bit_admin_field_identifiers unique'
);
test_assert_same(
    ['grupo', 'grupo_cantidad', 'grupo_1_nombre', 'grupo_2_nombre'],
    bit_admin_field_identifiers([
        'type' => 'yes_no_quantity_group',
        'name' => 'grupo',
        'quantity_name' => 'grupo_cantidad',
        'max' => 2,
        'fields' => [['name' => 'nombre']],
    ]),
    'bit_admin_field_identifiers derived group names'
);
test_assert_same(
    ['grupo_directo', 'grupo_directo_cantidad', 'grupo_directo_1_nombre', 'grupo_directo_2_nombre'],
    bit_admin_field_identifiers([
        'type' => 'quantity_group',
        'name' => 'grupo_directo',
        'quantity_name' => 'grupo_directo_cantidad',
        'max' => 2,
        'fields' => [['name' => 'nombre']],
    ]),
    'bit_admin_field_identifiers direct quantity names'
);
test_assert_same(
    ['planta_elect', 'mant5', 'mant6', 'mant7', 'plantaGroup'],
    bit_admin_field_identifiers(['type' => 'plant', 'name' => 'planta_elect']),
    'bit_admin_field_identifiers plant controls'
);
test_assert_same(
    ['vacaciones', 'vacaciones_colaborador'],
    bit_admin_field_identifiers([
        'type' => 'yes_no_detail_group',
        'name' => 'vacaciones',
        'fields' => [['name' => 'colaborador']],
    ]),
    'bit_admin_field_identifiers detail group controls'
);

$_POST = [
    'type' => 'text',
    'name' => 'campo_extra',
    'label' => 'Campo extra',
    'section' => 'Operaciones',
    'col' => 'invalid',
    'required' => '1',
];
[$adminField, $adminMessage] = bit_admin_field_from_post(['sedes' => ['PANCE']]);
test_assert_same('', $adminMessage, 'bit_admin_field_from_post message');
test_assert_same('campo_extra', $adminField['name'] ?? null, 'bit_admin_field_from_post name');
test_assert_same('col-md-6', $adminField['col'] ?? null, 'bit_admin_field_from_post col fallback');

$_POST = [
    'type' => 'subsection',
    'name' => 'etiqueta_admin',
    'label' => 'Etiqueta administrativa',
    'description' => 'Texto descriptivo',
    'section' => 'Operaciones',
    'required' => '1',
    'col' => 'col-md-3',
];
[$adminSubsection, $adminSubsectionMessage] = bit_admin_field_from_post(['sedes' => ['PANCE']]);
test_assert_same('', $adminSubsectionMessage, 'admin subsection message');
test_assert_same('Texto descriptivo', $adminSubsection['description'] ?? null, 'admin subsection description');
test_assert_same(false, $adminSubsection['required'] ?? null, 'admin subsection is not required');
test_assert_same('col-md-12', $adminSubsection['col'] ?? null, 'admin subsection full width');

$_POST = [
    'type' => 'yes_no',
    'name' => 'requiere_visita_admin',
    'label' => '¿Requiere visita?',
    'section' => 'Operaciones',
    'detail_name' => 'fecha_visita_admin',
    'detail_label' => 'Fecha de visita',
    'detail_type' => 'date',
];
[$adminYesNoDate, $adminYesNoDateMessage] = bit_admin_field_from_post(['sedes' => ['PANCE']]);
test_assert_same('', $adminYesNoDateMessage, 'admin yes_no date message');
test_assert_same('date', $adminYesNoDate['detail_type'] ?? null, 'admin yes_no date detail type');

$_POST = [
    'type' => 'yes_no_quantity_group',
    'name' => 'grupo_prueba',
    'label' => 'Grupo prueba',
    'section' => 'Operaciones',
    'group_fields' => [
        'name' => ['nombre', 'nombre'],
        'label' => ['Nombre uno', 'Nombre dos'],
        'type' => ['text', 'text'],
        'row_key' => ['gf_1', 'gf_2'],
        'required' => ['gf_1', 'gf_2'],
    ],
];
[$duplicateGroupField, $duplicateGroupMessage] = bit_admin_field_from_post(['sedes' => ['PANCE']]);
test_assert_same(null, $duplicateGroupField, 'bit_admin_field_from_post duplicate group field');
test_assert_same('Los nombres técnicos de los sub-campos no pueden repetirse.', $duplicateGroupMessage, 'bit_admin_field_from_post duplicate group message');

$_POST = [
    'type' => 'yes_no_quantity_group',
    'name' => 'grupo_reporte_no',
    'label' => 'Grupo con texto para No',
    'section' => 'Operaciones',
    'no_report_value' => 'No hubo registros durante la jornada',
    'group_fields' => [
        'name' => ['detalle'],
        'label' => ['Detalle'],
        'type' => ['simple_radio'],
        'row_key' => ['gf_1'],
        'required' => ['gf_1'],
    ],
];
[$adminQuantityNoField, $adminQuantityNoMessage] = bit_admin_field_from_post(['sedes' => ['PANCE']]);
test_assert_same('', $adminQuantityNoMessage, 'admin quantity group no report message');
test_assert_same('No hubo registros durante la jornada', $adminQuantityNoField['no_report_value'] ?? null, 'admin quantity group no report value');
test_assert_same('simple_radio', $adminQuantityNoField['fields'][0]['type'] ?? null, 'admin nested simple radio type');

$_POST = [
    'type' => 'quantity_group',
    'name' => 'grupo_cantidad_directa',
    'label' => 'Grupo con cantidad directa',
    'section' => 'Operaciones',
    'quantity_name' => 'cantidad_directa',
    'quantity_max' => '3',
    'zero_report_value' => 'No hubo registros directos',
    'group_fields' => [
        'name' => ['detalle'],
        'label' => ['Detalle'],
        'type' => ['text'],
        'row_key' => ['gf_1'],
        'required' => ['gf_1'],
    ],
];
[$adminDirectQuantityField, $adminDirectQuantityMessage] = bit_admin_field_from_post(['sedes' => ['PANCE']]);
test_assert_same('', $adminDirectQuantityMessage, 'admin direct quantity group message');
test_assert_same(true, $adminDirectQuantityField['required'] ?? null, 'admin direct quantity group is required');
test_assert_same(0, $adminDirectQuantityField['min'] ?? null, 'admin direct quantity group minimum');
test_assert_same('No hubo registros directos', $adminDirectQuantityField['zero_report_value'] ?? null, 'admin direct quantity group zero report value');

$_POST = [
    'label' => '¿Hubo visitas?',
    'no_report_value' => 'No hubo visitas en la sede',
];
[$baseQuantityOverride, $baseQuantityOverrideMessage] = bit_admin_base_override_from_post($quantityNoReportField, ['sedes' => []]);
test_assert_same('', $baseQuantityOverrideMessage, 'base quantity group no report override message');
test_assert_same('No hubo visitas en la sede', $baseQuantityOverride['no_report_value'] ?? null, 'base quantity group no report override');

$_ENV['BITACORA_PDF_TTL_DAYS'] = '0';
test_assert_same(null, bit_pdf_expiration_datetime(), 'bit_pdf_expiration_datetime disabled');

$tmpBase = sys_get_temp_dir() . '/bitacora_test_' . bin2hex(random_bytes(4));
mkdir($tmpBase . '/1/2026/07', 0777, true);
$pdfPath = $tmpBase . '/1/2026/07/test.pdf';
file_put_contents($pdfPath, '%PDF-test');
$_ENV['BITACORA_STORAGE_PATH'] = $tmpBase;
putenv('BITACORA_STORAGE_PATH=' . $tmpBase);
test_assert_same(realpath($pdfPath), bit_download_resolve_path('1/2026/07/test.pdf'), 'bit_download_resolve_path valid');
test_assert_same(null, bit_download_resolve_path('../.env'), 'bit_download_resolve_path traversal');
test_assert_same(null, bit_download_resolve_path('/1/2026/07/test.pdf'), 'bit_download_resolve_path absolute');
test_assert_same(realpath($pdfPath), queue_resolve_attachment('1/2026/07/test.pdf'), 'queue_resolve_attachment valid');
test_assert_same(null, queue_resolve_attachment('../.env'), 'queue_resolve_attachment traversal');
test_assert_same(QUEUE_STALE_LOCK_ERROR, 'El correo quedó bloqueado y superó el máximo de intentos.', 'QUEUE_STALE_LOCK_ERROR');

echo 'Tests OK' . PHP_EOL;
