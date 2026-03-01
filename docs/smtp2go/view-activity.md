

# View Account Activity

## Introducing Activity Search

The [/activity/search](https://developers.smtp2go.com/reference/search-activity) endpoint allows you to search email events from your account’s “Reports > Activity” page matching the criteria in your request.

Accounts on the free plan can search up to 5 days of event history and paid plans up to 30 days by default. If paid plans utilize the [Activity Duration](https://support.smtp2go.com/hc/en-gb/articles/27243944323737-Activity-Storage-Duration) feature to extend the storage (from 60 days up to 2 years) then event history will be available for the selected timeframe.

The request can return up to a maximum of 1,000 events by default and if the response exceeds that, a continue\_token will be provided to retrieve the remaining results.

## Parameters

The [/activity/search](https://developers.smtp2go.com/reference/search-activity) endpoint accepts 14 parameters with all listed below.

<Table align={["left","left","left"]}>
  <thead>
    <tr>
      <th>
        Parameter
      </th>

      <th>
        Type
      </th>

      <th>
        Description
      </th>
    </tr>
  </thead>

  <tbody>
    <tr>
      <td>
        start\_date
      </td>

      <td>
        date
      </td>

      <td>
        ISO-8601 formatted datetime which defaults to the current date at midnight. The range will be inclusive of start\_date and exclusive of end\_date. The timezone is UTC.
      </td>
    </tr>

    <tr>
      <td>
        end\_date
      </td>

      <td>
        date
      </td>

      <td>
        ISO-8601 formatted datetime which defaults to now. The timezone is UTC.
      </td>
    </tr>

    <tr>
      <td>
        search
      </td>

      <td>
        string
      </td>

      <td>
        If passed, will return all events for emails containing this string in the Email\_id, Subject, To, Recipient or BCC fields. To return events with one or more text values, separate the text with '|' (e.g. 'text1 | text2').
      </td>
    </tr>

    <tr>
      <td>
        search\_subject
      </td>

      <td>
        string
      </td>

      <td>
        If passed, will return all events for emails containing this string in the email subject.
      </td>
    </tr>

    <tr>
      <td>
        search\_sender
      </td>

      <td>
        string
      </td>

      <td>
        If passed, will return all events for emails containing this string in the email sender.
      </td>
    </tr>

    <tr>
      <td>
        search\_recipient
      </td>

      <td>
        string
      </td>

      <td>
        If passed, will return all events for emails containing this string in the email recipient.
      </td>
    </tr>

    <tr>
      <td>
        search\_usernames
      </td>

      <td>
        Array of strings
      </td>

      <td>
        If passed, will return all events for emails sent by this/these username/s.
      </td>
    </tr>

    <tr>
      <td>
        subaccounts
      </td>

      <td>
        Array of strings
      </td>

      <td>
        If passed, will return all events for emails sent by this/these subaccount/s.
      </td>
    </tr>

    <tr>
      <td>
        limit
      </td>

      <td>
        int32
      </td>

      <td>
        The maximum number of events to return (Max: 1000).
      </td>
    </tr>

    <tr>
      <td>
        continue\_token
      </td>

      <td>
        string
      </td>

      <td>
        If passed, will continue the search beyond the current page, using the same search parameters.
      </td>
    </tr>

    <tr>
      <td>
        only\_latest
      </td>

      <td>
        boolean
      </td>

      <td>
        If true, will only return the most recent event for each email returned. Default: false.
      </td>
    </tr>

    <tr>
      <td>
        only\_latest\_by\_sent
      </td>

      <td>
        boolean
      </td>

      <td>
        If true, will only return the most recent event for each email returned ordered by sent date.  Default: false
      </td>
    </tr>

    <tr>
      <td>
        event\_types
      </td>

      <td>
        array of strings
      </td>

      <td>
        If passed, will limit the returned events to the provided event types.\
        Values: 'processed', 'soft-bounced', 'hard-bounced', 'rejected', 'spam', 'delivered', 'unsubscribed', 'resubscribed', 'opened' and 'clicked.'
      </td>
    </tr>

    <tr>
      <td>
        include\_headers
      </td>

      <td>
        boolean
      </td>

      <td>
        Return the full email headers with the response.
      </td>
    </tr>

    <tr>
      <td>
        custom\_headers
      </td>

      <td>
        array of strings
      </td>

      <td>
        A list of header keys to parse out of the raw headers.
      </td>
    </tr>
  </tbody>
</Table>

<br />

## A search for bounced emails

In this search, we'll make use of only a few parameters covered above. However, you can combine many parameters to refine your search so it returns the particular data you need.

The search in this example will return soft and hard bounces encountered by a specific sending address within a defined period. To achieve this, the parameters used include the start\_date, end\_date, search\_sender, event\_types and limit (simply to limit the results to 1 for display purposes).

The example uses cURL. A range of languages are available which you can test and generate examples for on the “[Search Activity](https://developers.smtp2go.com/reference/search-activity)” page of the API Reference section.

In the request headers, we POST to the “[https://api.smtp2go.com/v3/activity/search”](https://api.smtp2go.com/v3/activity/search”) endpoint, define the content type of “application/json” and include a valid API Key for authentication. The search parameters are set in the request body as JSON objects.

## The Request

```curl cURL
curl --request POST \
     --url https://api.smtp2go.com/v3/activity/search \
     --header 'Content-Type: application/json' \
     --header 'X-Smtp2go-Api-Key: api-xxxxxxxxxxxx' \
     --header 'accept: application/json' \
     --data '
{
  "start_date": "2024-02-11",
  "end_date": "2024-02-13",
  "event_types": [
    "soft-bounced",
    "hard-bounced"
  ],
  "search_sender": "sender@example.com",
  "limit: 1
}
'

```

## Response

A 200 response indicates a successful request and it would look similar to:

```curl cURL
{
"request_id": "82aff18a-ca05-11ee-8a81-f23c9216ce11","data": {
  "events": [
    {
      "from": "sender@example.com",
      "recipient": "hello@example.com",
      "subaccount_name": "Master account",
      "email_id": "1rZPGW-u6wXBO-2m",
      "date": "2024-02-12T05:54:07Z",
      "event": "hard-bounced",
      "subject": "Test bounce",
      "username": "TestUser",
       "sender": "sender@example.com",
       "sender_full": "sender@example.com",
       "to": "hello@example.com",
      "smtp_response": "550-5.1.1 The email account that you tried to reach does not exist. Please try\\n550-5.1.1 double-checking the recipient's email address for typos or\\n550-5.1.1 unnecessary spaces. For more information, go to\\n550 5.1.1  https://support.google.com/mail/?p=NoSuchUser n13-20020a05622a040d00b0042bf4b5caf6si7967427qtx.480 - gsmtp",
      "host": "142.xxx.xxx.xx",
      "outbound_ip": "103.47.206.44",
      "byte_size": 1068
    }
  ],
  "total_events": 3,
  "continue_token": "eyJhbGciO***********"
}
}

```

Note: a `continue_token` was included in the response, as the search returned three results ("total\_events": 3) but a limit of 1 was set in the request so it would only show one result for display purposes. The `continue_token` indicates the results exceed the limit (either passed in the request or the default value). In the event that you receive a `continue_token` string, you can pass it along with all of the original request data to continue the search and retrieve the next batch of results.

A 400 response indicates a failure. The response will include an error code and details covering the cause of the error, similar to the below example where an incorrect event type was set:

```curl cURL
{
  "request_id": "ce3fc5b4-ca08-11ee-bd82-f23c93560c0e",
  "data": {
    "error": "An error occurred processing the json data you sent with the request. Please make sure the data conforms to the specification for this call (see the documentation here: https://developers.smtp2go.com/reference/introduction) and try again. Don't forget to set Content-Type to 'application/json'.",
    "error_code": "E_ApiResponseCodes.NON_VALIDATING_IN_PAYLOAD",
    "field_validation_errors": {
      "fieldname": "event_types",
      "message": "The field 'event_types' was expecting a value from the allowed list ['processed', 'soft-bounced', 'hard-bounced', 'rejected', 'spam', 'delivered', 'unsubscribed', 'resubscribed', 'opened', 'clicked'] but instead found '['example']', Please correct your JSON payload and try again."
    }
  }
}

```

The [activity/search](https://developers.smtp2go.com/reference/search-activity) endpoint can be used for simple requests such as the above or dive into more complex searches utilizing a range of parameters.

If you encounter an error when making a request, please submit a support ticket and provide the request\_id in the response along with your request code as these will help our team investigate.