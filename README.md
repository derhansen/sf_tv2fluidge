TemplaVoila migration to Fluidtemplate and Grid Element
=======================================================

## What is it?

A TYPO3 Extension with tools that can be helpful when migrating an existing website
from TemplaVoila to Fluidtemplate and Grid Elements

## Included modules

* Module to mark all unreferenced elements as deleted
* Module to migrate the content of a flexible content element to a Grid Element
* Module to migrate content from TemplaVoila columns to Fluidtemplate columns

## Who should use it?

This extension should be used by TYPO3 integrators with good knowledge of TemplaVoila, Fluidtemplates, Grid Elements
and TypoScript.

## Prerequisites

* A full backup of your TYPO3 website (database and files)!
* TemplaVoila version 1.8 installed and with old page module enabled
* ExtBase and Fluid installed
* Grid Elements installed (please ignore conflict with TemplaVoila!)

## Migration steps in short

1. Create Grid Elements for all Flexible Content Elements you wish to migrate
2. Create backend layouts and Fluidtemplates for all TemplaVoila page templates you wish to migrate
3. Delete all unreferenced elements
4. Migrate all Flexible Content Elements to Grid Elements
5. Migrate all TemplaVoila page templates to Fluidtemplate
6. Remove TemplaVoila

## Delete unreferenced elements

Many users working with TemplaVoila think, that the "unlink" function for content elements deletes the desired
content elements. Actually, the content element only gets unlinked from the TemplaVoila layout and persists
on the page. On the TemplaVoila tab "Non-used elements" all content elements are shown which that are not linked
on the actual page.

When migrating a TYPO3 website from TemplaVoila to Fluidtemplate, all unreferenced elements should be deleted,
since they are not shown on the output page. You can use this module to perform this action.

The action can safely be used, since it only flags all unreferenced elements as deleted.

## Migrate FCE

![FCE migration module](Documentation/Images/fce-migration.png)

This module migrates content from a Flexible Content Element to an existing Grid Element. If the Flexible
Content Element contains content columns, they can be remapped to content columns of the target Grid Element.

### Prerequisites

Before you can start with the migration, you must create a GridElement for each Flexible Content Element you wish
to migrate. If your Flexible Content Element contains JavaScript, you should include this somewhere in you page
TypoScript or Grid Element TypoScript.

**Flexible Content Elements with content columns only**

If your Flexible Content Element only constists of content columns (e.g. 2 column FCE), you should
create a new Grid Element with number of content columns from your Flexible Content Element

**Flexible Content Elements with flexform only**

If your Flexible Content Element only constists of flexform fields (input, images, TypoScript...), you should
create a new Grid Element and insert the flexform XML from the Flexible Content Element in the Grid Element.

When creating the TypoScript for the new Grid Element, you can use `field:flexform_` to get the flexform values.

Example:

```
20 = TEXT
20.data = field:flexform_field_text
```

**Flexible Content Elements with mixed flexform and content columns**

If your Flexible Content Element contains a mix of content columns and flexform, you should create a new Grid
Element and and insert the flexform XML from the Flexible Content Element in the Grid Element.

Then setup the gridelement like shown in the below.

Example:

```
1 <  lib.gridelements.defaultGridSetup
1 {
    columns {
      # colPos ID
      0 < .default
      0.wrap = <div class="content">|</div>
    }
    wrap.stdWrap.cObject = COA
    wrap.stdWrap.cObject {
      10 = TEXT
      10.data = field:flexform_field_text
    }
  }
```

### How does the migration work?

The FCE migration module finds all Flexible Content Elements of the selected type, changes the content type
to GridElement and sets the selected backend layout for the Grid Element. Then it copies the content from
`tx_templavoila_flex` to `pi_flexform`. If the selected Flexible Content Element has content columns, then all
content elements will be mapped to the selected content columns of the target Grid Element.

If you select to create shortcuts for content elements that are TemplaVoila references, then each matching content
element will be insered as a shortcut in the given content column.

## Migrate content

@todo - Insert image

This module migrates content from TemplaVoila content columns to backend layout content columns based on
Fluidtemplate.

### Prerequisites

Before you can start with the migration, you must create a backend layout and Fluidtemplate for each TemplaVoila
page template you wish to migrate.