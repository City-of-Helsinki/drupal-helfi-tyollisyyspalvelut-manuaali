CONTENTS OF THIS FILE
---------------------
   
 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers

 INTRODUCTION
------------

Drupal Diff module https://www.drupal.org/project/diff provide the capability to compare the difference between node revisions.
Since Drupal 8.7.x, taxonomy, block and media are all revisionable, but there is no UI available yet. This module introduces API and UI for comparing content revisions for content entities other than node.
This module consists of following sub modules:

* Block content diff UI plugin
Provide UI for comparing block contents.

* Media diff UI plugin
Provide UI for comparing media contents.

* Taxonomy diff UI plugin  
Provide UI for comparing taxonomy terms.

REQUIREMENTS
------------
Drupal core 8.7.7 and above.

Diff module 8.x-1.0 and above.

INSTALLATION
------------

* Install as you would normally install a contributed Drupal module.

CONFIGURATION
-------------
* Enable the submodules
* Tick the 'Create new revision' box for the Media type which you want to compare the revisions for.
* Tick the 'Create new revision' box for the Block type which you want to compare the revisions for.
* A new tab called 'Revisions' should show up in the Taxonomy term, Block and Media edit page, which is similar to the way how the Diff module works for Node content.

MAINTAINERS
-----------

Mingsong Hu (Mingsong) - https://www.drupal.org/u/mingsong