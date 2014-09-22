slack-integration
=================

A place where I put some integration scripts for the popular Slack messaging platform.


#Rally Bot

Pushes notifications from Rally whenever a comment is added to a ticket, and queries our Rally instance for defects, tasks and user stories. I have it configured to respond to a /rallyme slash command in Slack.

E.g.:
/rallyme DE12345
/rallyme US23456
/rallyme TA34567

Each of these returns a nicely formatted summary of the defect, story or task, including the owner/submitter, creation date, story title, project and description. For documents that have attachments, such as uploaded screenshots, the summary will include a link to the first available attachment.

#Image Bot / Gif Bot

This one is simple. It uses the google image search JSON API to query for images that match a keyword. It is configured to use the Safe Search feature, since we use this tool in the workplace. Being able to add some levity to our work chats with amusing images from the internet makes life more fun.

E.g.
/imageme kittens
/gifme roflcopter


#XKCD Bot

As geeks, we are well acquainted with the internet comic XKCD. This bot allows you to post an XKCD comic to the room, including the ALT text, which will be displayed beneath the image.

E.g.
/xkcd 100 will return the "family circus" episode of XKCD.
