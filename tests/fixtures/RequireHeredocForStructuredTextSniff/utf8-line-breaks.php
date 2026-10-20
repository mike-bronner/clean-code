<?php

// Each string puts a character ending in byte 0x85 (Greek upsilon, CF 85)
// directly before text shaped like a markdown line, an INI section and two
// setting lines. A byte-mode \R read that 0x85 byte as a line break. None of
// them is structured text.
$markdown = "Ein Satz υ# Überschrift";
$iniSection = "Ωυ[core]\nname = value";
$settings = "Ωυname = value\nΩυother = thing";

// The same text with a real newline after the upsilon is structured.
$markdownControl = "Ein Satz υ\n# Überschrift";
$iniSectionControl = "Ωυ\n[core]\nname = value";
$settingsControl = "Ωυ\nname = value\nΩυ\nother = thing";
