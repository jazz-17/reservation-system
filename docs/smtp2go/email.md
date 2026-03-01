API Features Overview

What can you do with the SMTP2GO API?

The API has many uses including sending emails or SMS messages, retrieving your account data and managing your account. Below is an overview of what you can do programmatically with the API.
Emails

There are two ways to send emails through the API; Standard Email (/email/send) and MIME Email (/email/mime).

With the /email/send endpoint, you pass us all of the components of an email, such as the sender, subject, body and recipient as a JSON Object. We then create the email and send it.

Alternatively, you may send an email composed as a MIME string. This can be done using a MIME library in your programming language of choice, before sending this to SMTP2GO via the /email/mime endpoint. SMTP2GO will use this exact MIME string to send the email.

/email/send
/email/mime
/email/search




# Send an Email

Learn how to send a standard email using the API.

## Introducing Standard Email

> 📘 Note
>
> There are two ways to send emails through the API which include using the **Standard Email** ([/email/send](https://developers.smtp2go.com/reference/send-standard-email)) endpoint or using the **MIME Email** ([/email/mime](https://developers.smtp2go.com/reference/send-mime-email)) endpoint.
>
> This basic guide uses the Standard Email option.

With the [/email/send](https://developers.smtp2go.com/reference/send-standard-email) endpoint, you pass us all of the components of an email, such as the *sender*, *body* and *recipient* as a JSON Object. We then create and send the email. The endpoint also allows you to make use of email templates - our [Get Started with Templates](https://developers.smtp2go.com/docs/getting-started-with-templates) guide is a great place to start.

The [/email/send](https://developers.smtp2go.com/reference/send-standard-email) endpoint accepts 13 parameters with 3 being required (marked with \*) - sender (string), to (array of strings) and subject (string). All parameters are:

> 👍 Three required parameters
>
> Sender (string), To (array of strings) and Subject (string) are the required parameters. Marked below with \*.

| Parameter       | Type             | Description                                                                                                                    |
| :-------------- | :--------------- | :----------------------------------------------------------------------------------------------------------------------------- |
| sender\*        | string           | The email address to send from                                                                                                 |
| to\*            | array of strings | An array of email addresses (up to 100) to send to                                                                             |
| cc              | array of strings | An array of email addresses (up to 100) to cc                                                                                  |
| bcc             | array of strings | An array of email addresses (up to 100) to bcc                                                                                 |
| subject\*       | string           | The subject of the email to be sent                                                                                            |
| html\_body      | string           | An HTML encoded email body                                                                                                     |
| text\_body      | string           | A plain text email body                                                                                                        |
| custom\_headers | array of objects | An array of custom header objects to be applied to the email                                                                   |
| attachments     | array of objects | An array of attachment objects to be attached to the email                                                                     |
| inlines         | array of objects | An array of images to be inlined into the email                                                                                |
| template\_id    | string           | The ID of the template you wish to use                                                                                         |
| template\_data  | json             | When a template\_id is provided, include the pass-through values in the format \{"variable1": "value1", "variable2": "value2"} |

<br />

## Craft a simple message

In this example, we'll POST to the [https://api.smtp2go.com/v3/email/send](https://api.smtp2go.com/v3/email/send) endpoint. For our first email, we'll use the three required parameters, along with the *text\_body* parameter.

## The request structure

The HTTP POST request is made up of two parts, the request headers and the request body containing the email message payload in JSON.

The request headers include:

* Endpoint URL ([https://api.smtp2go.com/v3/email/send](https://api.smtp2go.com/v3/email/send)).
* Content type (application/json).
* API Key for authentication (via the X-Smtp2go-Api-Key header). Alternatively, you could pass the API Key in the request data ("api\_key": "YourAPIKeyHere").

Example:

```curl Request headers
curl --request POST \
     --url https://api.smtp2go.com/v3/email/send \
     --header 'Content-Type: application/json' \
     --header 'X-Smtp2go-Api-Key: api-xxxx YOUR API KEY xxxx' \
     --header 'accept: application/json' \
```

The request body contains the message payload as a JSON Object.

Example:

```Text Request body
     --data '
{
  "sender": "email@example.com",
  "to": [
    "friend@example.com"
  ],
  "subject": "My First Email",
  "text_body": "Hello from the other side."
}
'
```

## The full request

Include the headers covered in the example below. Remember to enter your unique API Key, set the sender/from address, recipient/to address and enter some text in the *text\_body* parameter.

Though this guide uses cURL for examples, a wide number of options from *C++*, to *Javascript, Node* and more can be accessed throughout our [API Reference](https://developers.smtp2go.com/reference) documentation.

> 🚧 Ensure your "sender" address is valid
>
> Enter an email address that your account is authorised to send from (an email address from a verified sender). Check out the [Getting Started with the API guide](https://developers.smtp2go.com/docs/getting-started#sender-verification) for details.

```curl
curl --request POST \
     --url https://api.smtp2go.com/v3/email/send \
     --header 'Content-Type: application/json' \
     --header 'X-Smtp2go-Api-Key: api-xxxxxxxxxxxxxxxxxx' \
     --header 'accept: application/json' \
     --data '
{
  "sender": "email@example.com",
  "to": [
    "friend@example.com"
  ],
  "subject": "My First Email",
  "text_body": "Hello from the other side."
}
'
```

## Responses

> 👍 Success!
>
> This will be indicated by **a 200 OK** response

Similar to the below:

```curl
{
  "request_id": "aa253464-0bd0-467a-b24b-6159dcd7be60",
  "data": {
    "succeeded": 1,
    "failed": 0,
    "failures": [],
    "email_id": "1er8bV-6Tw0Mi-7h"
  }
}
```

The email\_id can be logged into your system and used when searching for the email.

A **400** response is encountered if the request fails and will include an explanation of the error. Similar to:

```curl
 {
  "request_id": "22e5acba-43bf-11e6-ae42-408d5cce2644",
  "data": {
    "error_code": "E_ApiResponseCodes.ENDPOINT_PERMISSION_DENIED",
    "error": "You do not have permission to access this API endpoint"
  }
}
```

## Take it further

Once you are sending a simple text email successfully, you can try using the *html\_body* parameter to send emails with HTML content or [add attachments](https://developers.smtp2go.com/docs/adding-attachments) using the *attachments* parameter.

To make use of email templates, view our “Get Started With Templates” page using the link below.



# Send a standard email

Send an email by passing a JSON email object

We recommend setting the parameter `fastaccept` to "true", as this is a much faster sending method, and will become the default method in the near future.

<br />

*Note - when using the `schedule` or `fastaccept` properties the email is queued, a total of 50,000 queued emails can be queued at any one time.*

# OpenAPI definition

```json
{
  "openapi": "3.1.0",
  "info": {
    "title": "SMTP2GO API v3.0.3",
    "version": "3.0.3"
  },
  "servers": [
    {
      "url": "https://api.smtp2go.com/v3",
      "description": "Regionless"
    },
    {
      "url": "https://us-api.smtp2go.com/v3",
      "description": "US Region"
    },
    {
      "url": "https://eu-api.smtp2go.com/v3",
      "description": "EU Region"
    }
  ],
  "components": {
    "securitySchemes": {
      "sec0": {
        "type": "apiKey",
        "in": "header",
        "name": "X-Smtp2go-Api-Key",
        "x-default": ""
      }
    }
  },
  "security": [
    {
      "sec0": []
    }
  ],
  "paths": {
    "/email/send": {
      "post": {
        "tags": [
          "EMAILS"
        ],
        "summary": "Send a standard email",
        "description": "Send an email by passing a JSON email object",
        "operationId": "send-standard-email",
        "requestBody": {
          "content": {
            "application/json": {
              "schema": {
                "type": "object",
                "required": [
                  "sender",
                  "to",
                  "subject"
                ],
                "example": {
                  "to": [
                    "Jane Jones <jane@example.com>"
                  ],
                  "sender": "John Smith <john@example.com>",
                  "subject": "My Test Email",
                  "html_body": "<h1>Test <img src=\"cid:mypicture.jpg\" /></h1>",
                  "text_body": "Test",
                  "attachments": [
                    {
                      "filename": "report.pdf",
                      "mimetype": "application/pdf",
                      "fileblob": "bm90IGFjdHVhbGx5IGEgcGRm..."
                    }
                  ],
                  "inlines": [
                    {
                      "filename": "mypicture.jpg",
                      "mimetype": "image/jpeg",
                      "url": "https://myserver.com/mypicture.jpg"
                    }
                  ],
                  "schedule": "2025-09-10 13:15:00 +1200"
                },
                "properties": {
                  "sender": {
                    "type": "string",
                    "description": "The name and email address to send from, in the format `Name <name@example.com>`"
                  },
                  "to": {
                    "type": "array",
                    "description": "An array of names and email addresses (up to 100) to send to, in the format `Name <name@example.com>`",
                    "items": {
                      "type": "string"
                    }
                  },
                  "cc": {
                    "type": "array",
                    "description": "An array of names and email addresses (up to 100) to CC, in the format `Name <name@example.com>`",
                    "items": {
                      "type": "string"
                    }
                  },
                  "bcc": {
                    "type": "array",
                    "description": "An array of names and email addresses (up to 100) to BCC, in the format `Name <name@example.com>`",
                    "items": {
                      "type": "string"
                    }
                  },
                  "subject": {
                    "type": "string",
                    "description": "The subject of the email to be sent"
                  },
                  "html_body": {
                    "type": "string",
                    "description": "A HTML encoded email body. Either html_body or text_body is required if template_id is not passed.<br><br> <strong>Warning:</strong><ul><li>To correctly track clicking of URLs you must:  <ul><li>Enable click tracking for the API key</li>  <li>Insert a full anchor HTML element (not just the URL)</li> <li>Include \"https://\" at the start of the HREF property</li> </ul></ul>"
                  },
                  "text_body": {
                    "type": "string",
                    "description": "A plain text email body. Either html_body or text_body is required if template_id is not passed"
                  },
                  "custom_headers": {
                    "type": "array",
                    "description": "An array of custom header objects to be applied to the email. For example, a `Reply-To` email can be specified here, with header `Reply-To` and value `name@example.com`. For sending purposes the following headers are not allowed `Content-Type`, `Content-Transfer-Encoding` and `MIME-Version`.",
                    "items": {
                      "properties": {
                        "header": {
                          "type": "string",
                          "description": "Custom header to add to the email"
                        },
                        "value": {
                          "type": "string",
                          "description": "Custom header value to set"
                        }
                      },
                      "required": [
                        "header",
                        "value"
                      ],
                      "type": "object"
                    }
                  },
                  "attachments": {
                    "type": "array",
                    "description": "An array of attachment objects to be attached to the email",
                    "items": {
                      "properties": {
                        "filename": {
                          "type": "string",
                          "description": "The filename to use for this binary data"
                        },
                        "fileblob": {
                          "type": "string",
                          "description": "The Base64 encoded binary data of the file. Required if no url is specified."
                        },
                        "mimetype": {
                          "type": "string",
                          "description": "The mimetype of the binary data"
                        },
                        "url": {
                          "type": "string",
                          "description": "A URL pointing to the attachment data. The data is directly retrieved by our system, and cached for fast re-use for 24 hours. Required if no fileblob is specified"
                        }
                      },
                      "required": [
                        "filename"
                      ],
                      "type": "object"
                    }
                  },
                  "inlines": {
                    "type": "array",
                    "description": "An array of images to be inlined into the email. Use an image in content as `<img src=\"cid:filename\"/>`",
                    "items": {
                      "properties": {
                        "filename": {
                          "type": "string",
                          "description": "The filename to use for this binary data"
                        },
                        "fileblob": {
                          "type": "string",
                          "description": "The Base64 encoded binary data of the file. Required if no url is specified."
                        },
                        "mimetype": {
                          "type": "string",
                          "description": "The mimetype of the binary data"
                        },
                        "url": {
                          "type": "string",
                          "description": "A URL pointing to the attachment data. The data is directly retrieved by our system, and cached for fast re-use for 24 hours. Required if no fileblob is specified"
                        }
                      },
                      "required": [
                        "filename"
                      ],
                      "type": "object"
                    }
                  },
                  "template_id": {
                    "type": "string",
                    "description": "The ID of the template you wish to use"
                  },
                  "template_data": {
                    "type": "string",
                    "description": "When a template_id is provided, include the pass-through values in the format `{\"variable1\": \"value1\", \"variable2\": \"value2\"}`",
                    "format": "json"
                  },
                  "schedule": {
                    "type": "string",
                    "description": "A timestamp that when passed allows you to schedule an email for sending. Must be in the future and within the next 3 days.<br />The api response will include a `schedule_id` property which can then be used alongside webhooks (An `X-Smtp2go-Schedule-Id` header is added to the sent email corresponds to this id)"
                  },
                  "fastaccept": {
                    "type": "boolean",
                    "default": false,
                    "description": "If true, the email will be accepted immediately and sent in a background process. Use webhooks if you need information about final delivery to the recipient. This will soon become the default method of sending via API."
                  }
                }
              }
            }
          }
        },
        "responses": {
          "200": {
            "description": "Email sent",
            "content": {
              "application/json": {
                "examples": {
                  "Example": {
                    "value": {
                      "request_id": "aa253464-0bd0-467a-b24b-6159dcd7be60",
                      "data": {
                        "succeeded": 1,
                        "failed": 0,
                        "failures": [],
                        "email_id": "1u0SwL-B9zBpi9ffUq-JAB2"
                      }
                    }
                  }
                },
                "schema": {
                  "type": "object",
                  "required": [
                    "request_id",
                    "data"
                  ],
                  "properties": {
                    "request_id": {
                      "type": "string",
                      "example": "aa253464-0bd0-467a-b24b-6159dcd7be60"
                    },
                    "data": {
                      "type": "object",
                      "required": [
                        "email_id"
                      ],
                      "properties": {
                        "failed": {
                          "type": "integer",
                          "example": 0,
                          "default": 0,
                          "description": "The number of emails that failed to send.<br><br>Note: An email with multiple recipients is classed as 1 email. Only returned if 'fastaccept' is false."
                        },
                        "failures": {
                          "type": "array",
                          "description": "An array containing any error messages encountered during sending. Only returned if 'fastaccept' is false."
                        },
                        "succeeded": {
                          "type": "integer",
                          "example": 1,
                          "default": 0,
                          "description": "The number of emails that were successfully sent.<br><br>Note: An email with multiple recipients is classed as 1 email. Only returned if 'fastaccept' is false."
                        },
                        "email_id": {
                          "type": "string",
                          "example": "1u0SwL-B9zBpi9ffUq-JAB2",
                          "description": "The email ID generated if successfully sent"
                        },
                        "schedule_id": {
                          "type": "string",
                          "example": "caa928f4-24ec-4a68-bcfc-1fd2596342f0",
                          "description": "The schedule ID generated if queued for sending. Only returned if 'schedule' is passed."
                        }
                      }
                    }
                  }
                }
              }
            }
          },
          "400": {
            "description": "400",
            "content": {
              "application/json": {
                "examples": {
                  "Example": {
                    "value": {
                      "request_id": "22e5acba-43bf-11e6-ae42-408d5cce2644",
                      "data": {
                        "error_code": "E_ApiResponseCodes.ENDPOINT_PERMISSION_DENIED",
                        "error": "You do not have permission to access this API endpoint"
                      }
                    }
                  }
                },
                "schema": {
                  "type": "object",
                  "properties": {
                    "request_id": {
                      "type": "string",
                      "example": "22e5acba-43bf-11e6-ae42-408d5cce2644"
                    },
                    "data": {
                      "type": "object",
                      "properties": {
                        "error_code": {
                          "type": "string",
                          "example": "E_ApiResponseCodes.ENDPOINT_PERMISSION_DENIED"
                        },
                        "error": {
                          "type": "string",
                          "example": "You do not have permission to access this API endpoint"
                        }
                      }
                    }
                  }
                }
              }
            }
          }
        },
        "deprecated": false
      }
    }
  },
  "x-readme": {
    "headers": [
      {
        "key": "Content-Type",
        "value": "application/json"
      }
    ],
    "explorer-enabled": true,
    "proxy-enabled": true,
    "samples-enabled": true
  }
}
```