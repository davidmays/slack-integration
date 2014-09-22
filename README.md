slack-integration
=================

Some integration scripts for the popular Slack messaging platform.

## Features

### Rally Bot
Pushes notifications from Rally and allows users to fetch ticket details. Notifications are sent whenever:

1. a comment is added to a ticket
2. a new defect, user story, or test case is created
3. a user story changes state

The bot uses a combination of Rally's _state_ and _ready_ fields to track the progress of user stories. When a story's state is set to "In-Progress" and the ready field is checked, rallybot will announce that the story is ready for testing. When the QA team has completed testing, they may either: 1) uncheck the ready flag to have rallybot announce that the story needs work, or 2) set the story to "Completed" to notify the Product Owner that it is ready for acceptance.

> **Note**: Rally automatically sets a story's state to "Completed" when all of its tasks are completed, so be sure to leave at least one open task in order to correctly track stories with defects.

The bot can also be configured to respond to a /rallyme slash command to query our Rally instance for defects, tasks and user stories.

E.g.:
/rallyme DE12345
/rallyme US23456
/rallyme TA34567

Each of these returns a nicely formatted summary of the defect, story or task, including the owner/submitter, creation date, story title, project and description. For documents that have attachments, such as uploaded screenshots, the summary will include a link to the first available attachment.

### Image Bot / Gif Bot
This one is simple. It uses the google image search JSON API to query for images that match a keyword. It is configured to use the Safe Search feature, since we use this tool in the workplace. Being able to add some levity to our work chats with amusing images from the internet makes life more fun.

E.g.
/imageme kittens
/gifme roflcopter

### XKCD Bot
As geeks, we are well acquainted with the internet comic XKCD. This bot allows you to post an XKCD comic to the room, including the ALT text, which will be displayed beneath the image.

E.g.
/xkcd 100 will return the "family circus" episode of XKCD.

## Installation

1. Clone this repository to your server

2. Create your config file from the default template:

   ```
   cd scripts/config
   cp default.config.php config.php
   ```

3. Create a new incoming webhook in Slack

   **Note**: Leave the channel to post to set to "#general", our scripts use channel-overrides to respond wherever the user issues a slash command.

4. Edit your config file with the incoming webhook's unique webhook URL

5. Add a slash command to Slack for each feature (for example: "When a user enters /rallyme, POST to http://example.com/rallyme.php")

   **Note**: The scripts will respond to requests over POST or GET.

6. If you are implementing Rally Bot, add your credentials to the config file

