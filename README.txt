BLOCK Vitrina
============================
A block to display a list of courses and their general information.

Package tested in: moodle 4.1, 4.2 and 4.3.

QUICK INSTALL
==============
Download zip package, extract the vitrina folder and upload this folder into blocks/.

ABOUT
=============
Developed by: David Herney - david dot herney at bambuco dot co
GIT: https://github.com/bambuco/moodle-block_vitrina

FEATURES
============
- Multilanguage filter compatibility.
- Integration with payment gateway components.
- Include related courses.
- Recognize Youtube and Vimeo media URL.
- Premium users management.

IN VERSION
=============
2023042618:
- Compatibility with enrol_customgr

2023042616:
- Change enrolment when change enrol state or dates in premiumenrolledcourse.
- Unenroll premium users if their membership expires.

2023042615:
- Moodle code rules applied.
- Enrollment date for premium courses ends when the membership ends.

2023042614:
- Setting to related courses limit.

2023042613:
- Include image as a posible media in course detail.
- The cost of premium courses is not shown to premium users.
- All courses are enabled for premium users if the premium courses functionality is not used.

2023042612:
- Extend shop plugins.
- Filter by fulltext in URL with the "q" param.

2023042611:
- Categories filter can be set to view as tree.

2023042610:
- Custom cost formater.

2023042609:
- Integration with local_buybee (shopping cart).
- Decimal points to format the course cost.

2023042608:
- Support to socialnetworks metadata.

2023042607:
- New {urlencoded} tag to build share link to social network.

2023042606:
- Support for payment success URL.

2023042605:
- Media poster included.

2023042604:
- Choice static filters.

2023042603:
- Filters in the catalog page.
- Full custom fields support in the detail page.

2023042602:
- Improve in settings fields selection.

2023042601:
- New views of courses in the general presentation of the block.
- New default sort admin setting.

2023042600:
- First version (fork from: https://github.com/cocreatic/moodle-block_greatcourses)
