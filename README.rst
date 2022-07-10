================
inesonic-history
================
You can use this plugin to track history of user activity.  The plugin is
is intended to be used in conjunction with other plugins and provides very
limited capabilities by itself.

You may also be interested in the
`Inesonic Logger Plugin <https://github.com/tuxidriver/inesonic-logger>`
which provides similar capabilities but geared towards logging for
debug and development.  Both plugins were developed for the
`Inesonic company website <https://inesonic.com>`.


Supported Fields
================
You can add history records to WordPress containing the following fields:

* The user ID
* A timestamp for the event
* A free form event type string (up-to 40 characters in length)
* Additional text for the event, up-to 65535 characters in length.

Currently the plugin will only track when a given user is removed from
WordPress, adding a ``USER_DELETED`` event type with basic identifying
information about the user.  Note that an optional company name will be
included if a ``company`` user meta field exists for the user.


Usage From The Admin Panel
==========================
You can view the history for a user, in time order by clicking on the
"History" link added to each user on the User page of the WordPress
admin panel.  Clicking this link will bring up history for the requested
user.  You can also view the history for *all* users by clicking on the
"Show History For All Users" link on the events page displayed for any
given user.


Supported Functions
===================
This plugin is intended to be used as the basis for a larger site that
needs to track history of its users.   You can add your own history
records using the ``inesonic_add_history`` action discussed below.

Currently the plugin currently supports a single function intended to
support requirements of the GPDR.

When a user is deleted from WordPress, this plugin will delete all the
history for the user.  The plugin will then add a single history record with
the event type ``USER_DELETED``.  Additional data will include the user's
first name, user's last name, an optional company, and the user's email
address.

The company name will only be included if a ``company`` user meta exists for
the user.


Adding History Records
======================
You can add history records as desired by using the ``inesonic_add_history``
action.

To add history, simply trigger the action with the user ID, event type string,
and any additional data you wish to supply with the event.  Below is an simple
example:

.. code-block:: php

   add_action('set_user_role', 'user_role_changed');

   . . .

   function user_role_changed($user_id, $role, $old_roles) {
       do_action(
           'inesonic_add_history',
           'ROLE_CHANGED',
           end($old_roles) . ' -> ' . $role
       );
   });
