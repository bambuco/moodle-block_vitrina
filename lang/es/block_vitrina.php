<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.


/**
 * Strings for component 'block_vitrina', language 'es'
 *
 * @package   block_vitrina
 * @copyright 2020 David Herney @ BambuCo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Vitrina de cursos';

// Capabilities.
$string['vitrina:addinstance'] = 'Adicionar un nuevo bloque de Vitrina de cursos';
$string['vitrina:myaddinstance'] = 'Adicionar un nuevo bloque de Vitrina de cursos a Dashboard';

$string['privacy:metadata'] = 'El bloque de vitrina de cursos no almacena datos personales.';

$string['coursecategory'] = 'Categoría de curso';
$string['customtitle'] = 'Título personalizado';
$string['amountcourses'] = 'Cantidad de cursos';
$string['amountcourses_help'] = 'Cantidad de cursos a mostrar en la vista general.';
$string['thematicfield'] = 'Campo Temáticas';
$string['thematicfield_help'] = 'El nombre del campo para cargar las temáticas del curso.';
$string['unitsfield'] = 'Campo Contenidos';
$string['unitsfield_help'] = 'El nombre del campo para cargar las unidades de contenidos del curso.';
$string['socialnetworks'] = 'Redes sociales';
$string['socialnetworks_help'] = 'Una red social por línea, con la siguiente estructura: nombre-icono|URL.
La URL puede usar las claves: {url} y {name}.';
$string['requirementsfield'] = 'Campo Requisitos';
$string['requirementsfield_help'] = 'El nombre del campo para cargar los requisitos del curso';
$string['licensefield'] = 'Campo Licencia';
$string['licensefield_help'] = 'El nombre del campo para cargar la licencia del curso';
$string['newblocktitle'] = 'Vitrina de cursos';
$string['showmore'] = 'Ver más...';
$string['singleamountcourses'] = 'Cantidad simple';
$string['singleamountcourses_help'] = 'Cantidad de cursos a mostrar en la vista por defecto de bloque.';
$string['notvisible'] = 'Este curso no está disponible';
$string['coursedetail'] = 'Detalles del curso';
$string['mediafield'] = 'Campo Medio';
$string['mediafield_help'] = 'El nombre del campo para cargar una URL de video del curso';
$string['durationfield'] = 'Campo Duración';
$string['durationfield_help'] = 'El nombre del campo para cargar la duración del curso.';
$string['expertsfield'] = 'Campo de Expertos';
$string['expertsfield_help'] = 'El nombre del campo para especificar los expertos del curso.';
$string['expertsshortfield'] = 'Campo resumen de expertos';
$string['expertsshortfield_help'] = 'El nombre de un campo que indica la versión resumida de los expertos.';
$string['sharecourse'] = 'Comparte este curso';
$string['sharecoursedesc'] = '¿Conoces a alguien que le pueda gustar este curso?<br />
<strong>Cuéntale sobre él</strong>';
$string['license-cc-0'] = 'Dominio público';
$string['license-cc-by'] = 'Creative Commons Atribución 4.0 Licencia Internacional';
$string['license-cc-by-nd'] = 'Creative Commons Atribución-SinObrasDerivadas 4.0 Licencia Internacional';
$string['license-cc-by-sa'] = 'Creative Commons Atribución-CompartirIgual 4.0 Licencia Internacional';
$string['license-cc-by-nc'] = 'Creative Commons Atribución-NoComercial 4.0 Licencia Internacional.';
$string['license-cc-by-nc-sa'] = 'Creative Commons Atribución-NoComercial-CompartirIgual 4.0 Licencia Internacional';
$string['license-cc-by-nc-nd'] = 'Creative Commons Atribución-NoComercial-SinObrasDerivadas 4.0 Licencia Internacional';
$string['enrolled'] = '¡Ya se encuentra matriculado en el curso!';
$string['enrollrequired'] = '¡Matricúlate para tomar el curso!';
$string['enroll'] = 'Matricular';
$string['notenrollable'] = '¡Curso no disponible para nuevas matrículas!';
$string['gotocourse'] = 'Ir al curso';
$string['catalog'] = 'Catálogo de cursos';
$string['returntocatalog'] = 'Ir al catálogo de cursos';
$string['greats'] = 'Destacados';
$string['recents'] = 'Nuevos';
$string['defaultsort'] = 'Todos';
$string['categories'] = 'Categorias';
$string['categories_help'] = 'Categorias en las que buscar los cursos. Se usa el id numérico de la categoría y la coma como separador.';
$string['summary'] = 'Resumen';
$string['summary_help'] = 'El contenido es mostrado en la parte superior de la lista de cursos.';
$string['detailinfo'] = 'Info detalle';
$string['detailinfo_help'] = 'Información general para la vista de detalles de cursos.';
$string['coverimagetype'] = 'Imagen de vista previa';
$string['coverimagetype_help'] = 'Opción cuando el curso no tiene una imagen asignada.';
$string['coverimagetype_default'] = 'Imágen por defecto del bloque';
$string['coverimagetype_generated'] = 'Textura generada aleatoriamente';
$string['coverimagetype_none'] = 'Ninguna';
$string['waiting'] = 'Próximamente';
$string['viewall'] = 'Ver todos';
$string['completed'] = 'Curso completado';
$string['paymenturlfield'] = 'Campo de URL de pago';
$string['paymenturlfield_help'] = '';
$string['settingsheaderappearance'] = 'Apariencia';
$string['settingsheaderfields'] = 'Campos del curso';
$string['settingsheaderpayment'] = 'Opciones para cursos de pago';
$string['premiumfield'] = 'Campo de usuario premium';
$string['premiumfield_help'] = '';
$string['premiumvalue'] = 'Valor de usuario premium';
$string['premiumvalue_help'] = 'El valor que debe tener el usuario en su perfil en el campo seleccionado como premium';
$string['premium'] = 'Premium';
$string['paymentrequired'] = 'Este curso es de pago';
$string['paymentbutton'] = 'Pagar';
$string['ratingslabel'] = '{$a} calificaciones';
$string['commentslabel'] = '{$a} comentarios';
$string['countstars'] = '{$a} estrellas';
$string['commentstitle'] = 'Comentarios más recientes';
$string['daystoupcoming'] = 'Días para cursos próximos';
$string['daystoupcoming_help'] = 'Número de días para considerar cursos próximos como activos para mostrar en la vista resumen del bloque.';
$string['relatedcourses'] = "Cursos relacionados";
$string['courselinkcopiedtoclipboard'] = "El enlace se ha copiado al portapapeles";
$string['requireauth'] = 'Debe <a href="{$a}">acceder</a> primero para poder matricular este curso';
$string['hascourseview'] = '¡Tienes acceso a este curso con tu usuario actual!';
$string['templatetype'] = 'Tipo de plantilla';
$string['templatetype_help'] = 'Elija una plantilla para ajustar la apariencia de las páginas del bloque';
$string['comment_by'] = 'Por {$a}';
$string['selectcategories'] = 'Elija una categoría de curso';
$string['showmorecomments'] = 'Ver más comentarios';
$string['showlesscomments'] = 'Ver menos';
