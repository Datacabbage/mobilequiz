<?php
/*
+-----------------------------------------------------------------------------+
| MobileQuiz ILIAS plug-in for audience feedback with mobile devices          |
+-----------------------------------------------------------------------------+
| Copyright 2016 Daniel Schoen                                                |
|                                                                             |
| MobileQuiz is free software: you can redistribute it and/or modify          |
| it under the terms of the GNU General Public License as published by        |
| the Free Software Foundation, either version 3 of the License, or           |
| (at your option) any later version.                                         |
|                                                                             |
|                                                                             |
| MobileQuiz is distributed in the hope that it will be useful,               |
| but WITHOUT ANY WARRANTY; without even the implied warranty of              |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the                |
| GNU General Public License for more details.                                |
|                                                                             |
| You should have received a copy of the GNU General Public License           |
| along with MobileQuiz.  If not, see <http://www.gnu.org/licenses/>.         |
+-----------------------------------------------------------------------------+
*/

// +--------------------------------------------------------------------------+
// |   Parameters for the AJAX interface                                      |
// +--------------------------------------------------------------------------+

// shared secret for ajax interface
define("AJAX_INTERFACE_SECRET", "1238dhsh27egkdad8w");

// time steps between the charts are dynamically updated
define("AJAX_CHART_UPDATE_TIME", "5000");

// +--------------------------------------------------------------------------+
// |   Configuration of the Students Frontend part                            |
// +--------------------------------------------------------------------------+

// Change path when frontend is located somewhere else
define("FRONTEND_PATH", "Customizing/global/plugins/Services/Repository/RepositoryObject/MobileQuiz/frontend/");

// +--------------------------------------------------------------------------+
// |   Quiz Constants - DO NOT TOUCH - unless you know what you do            |
// +--------------------------------------------------------------------------+

define("QUESTION_TYPE_MULTI",   "1");   // Multi Choice
define("QUESTION_TYPE_SINGLE",  "2");   // Single Choice
define("QUESTION_TYPE_NUM",     "3");   // Numeric
define("QUESTION_TYPE_TEXT",    "4");   // Text Question

define("CHOICE_TYPE_INCORRECT", "0");   // Wrong Choice
define("CHOICE_TYPE_CORRECT",   "1");   // Correct Choice
define("CHOICE_TYPE_NEUTRAL",   "2");   // Neutral Choice

define("ROUND_STATUS_INACTIVE", "inactive");
define("ROUND_STATUS_ACTIVE",   "active");
define("ROUND_STATUS_PASSIVE",  "passive");

define("NUMERIC_MAX_NUMBER_OF_BUCKETS", "20"); // max number of Buckets displayed in Numeric Questions

?>
