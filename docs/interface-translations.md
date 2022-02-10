# Interface translations

## Export

###English:\
`lando drush locale:export en --types=customized > ./translations/en-interface-translations.po`\
###Finnish:\
`lando drush locale:export fi --types=customized > ./translations/fi-interface-translations.po`\
###Swedish:\
`lando drush locale:export sv --types=customized > ./translations/sv-interface-translations.po`

## Import

### Staging / Production:
Importing is done automatically at deployment.

### Local environment:\
`drush -y locale:import fi ./translations/fi-interface-translations.po`\
`drush -y locale:import sv ./translations/sv-interface-translations.po`\
`drush -y locale:import en ./translations/en-interface-translations.po`\
    