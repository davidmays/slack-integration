slack-integration
=================

A place where I put some integration scripts for the popular Slack messaging platform.


#Rally Bot

We use this to query our Rally instance for defects, tasks and user stories. It can be configured to respond to Slack [slash command](https://slack.zendesk.com/hc/en-us/articles/201259356-Slash-Commands) or to messages posted to a channel that start with a Rally ticket's formatted id, e.g.:
```
/rallyme DE12345
/rallyme US23456 project
TA34567 owner description
```
Entering the id of an artifact without any arguments returns a nicely formatted summary of the defect, story or task, including the owner/submitter, creation date, story title, project and description. For documents that have attachments, such as uploaded screenshots, the summary will include a link to the first available attachment. Otherwise, adding one or more field names after the id will filter the summary to just display the requested fields.

#Image Bot / Gif Bot

This one is simple. It uses the google image search JSON API to query for images that match a keyword. It is configured to use the Safe Search feature, since we use this tool in the workplace. Being able to add some levity to our work chats with amusing images from the internet makes life more fun.
```
/imageme kittens
/gifme roflcopter
```

#XKCD Bot

As geeks, we are well acquainted with the internet comic XKCD. This bot allows you to post an XKCD comic to the room, including the ALT text, which will be displayed beneath the image.

E.g. `/xkcd 100` will return the "family circus" episode of XKCD.
