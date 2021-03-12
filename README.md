# lions-lotto
Wordpress plugin for managing a small lottery


My first attempt at writing a wordpress plugin.

This plugin should set up a database to manage what numbers map to what users

## User
* See what tickets I've bought
* See what results are
* See if I've won anything
* Purchase a ticket

## Admin
* See what tickets have been sold and to whom
* Upload a result
* See what results are
* Manually assign a ticket to a non-user. (Someone who has paid by cash/cheque)
* Generate a result? 
* Broadcast a result to all registered users.

## Stripe Integration
* Download list of payments
* purchase flow

## Database
* tickets->id
* users->(id
* tickets_user->(id, owner, date, state) 500
* results->date ticket_id user_id
