# Modules

Provides module documentation from modules used in project.

## Custom

### Service Manual Workflow
service_manual_workflow

Description: Provides Events, EventSubscribers and services.

#### Events

File: ServiceModerationEvent.php

Description: Provides dynamic event when State has been updated.

Event names are generated service_manual_workflow.{from_state}.to.{to_state} template. Example: service_manual_workflow.draft.to.ready_to_publish

#### EventSubscribers

File: ServiceReadyToPublishSusbscriber.php

Description: Subscribes service_manual_workflow.draft.to.ready_to_publish and sends notification messages to group administration.

## Contrib

# Patches
