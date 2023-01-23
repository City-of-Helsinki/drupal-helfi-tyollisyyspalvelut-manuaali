# Test case document

## Critical features
- Organization: group admins and editors can create service content and edit all fields
- Service provider: group admin and editors can create service content but can't update fields under "specialist" tab 
- Group admins can invite new users to group and remove users from their own groups.
- Group admin can't administer users or content from other groups.
- Organization Group admins can administer content from sub groups.
- Editors can edit content in a group they are member of
- Organization: editors can publish content
- Service provider: editors can't publish content
- Service provider: when editor changes service status to ready to publish organization admin users in service provider group receive mail notifications.
- Users can't see Basic page content that is marked for roles other than theirs
- Specialist editors can view and edit fields under Specialist-tab
- Specialists can see "internal guide" fields in Service node.
- Logged-in users can add services to favorites
- User expiry notification works as expected
- 

## Availability tests
- Site is available and there's no visible errors on front page
- Solr search server is available and search finds items
- Service content is available and no errors visible