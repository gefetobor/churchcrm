/// <reference types="cypress" />

function extractCollection(body, key) {
    if (body && Array.isArray(body[key])) {
        return body[key];
    }
    if (Array.isArray(body)) {
        return body;
    }
    if (body && typeof body === "object") {
        return Object.values(body);
    }
    return [];
}

describe("API Private Calendar Events", () => {
    beforeEach(() => {
        cy.setupAdminSession();
    });

    it("GET /api/events/locations returns a valid collection", () => {
        cy.makePrivateAdminAPICall("GET", "/api/events/locations", null, 200).then((response) => {
            const locations = extractCollection(response.body, "Locations");
            expect(locations).to.be.an("array");
            if (locations.length > 0) {
                expect(locations[0]).to.have.property("LocationId");
                expect(locations[0]).to.have.property("LocationName");
            }
        });
    });

    it("creates and updates event location fields", () => {
        let eventTypeId;
        let calendarId;
        let locationId = null;
        const titleBase = `API Location Test ${Date.now()}`;

        cy.makePrivateAdminAPICall("GET", "/api/events/types", null, 200)
            .then((typesResponse) => {
                const eventTypes = extractCollection(typesResponse.body, "EventTypes");
                expect(eventTypes.length, "at least one event type").to.be.greaterThan(0);
                eventTypeId = eventTypes[0].Id;
            })
            .then(() => cy.makePrivateAdminAPICall("GET", "/api/calendars", null, 200))
            .then((calendarsResponse) => {
                const calendars = extractCollection(calendarsResponse.body, "Calendars");
                expect(calendars.length, "at least one calendar").to.be.greaterThan(0);
                calendarId = calendars[0].Id;
            })
            .then(() => cy.makePrivateAdminAPICall("GET", "/api/events/locations", null, 200))
            .then((locationsResponse) => {
                const locations = extractCollection(locationsResponse.body, "Locations");
                if (locations.length > 0) {
                    locationId = locations[0].LocationId;
                }

                const createPayload = {
                    Title: `${titleBase} - created`,
                    Type: eventTypeId,
                    Desc: "<p>Created from Cypress</p>",
                    Start: new Date().toISOString(),
                    End: new Date(Date.now() + 3600000).toISOString(),
                    Text: "<p>Body text</p>",
                    PinnedCalendars: [calendarId],
                    LocationText: "123 Cypress Ave, Test City",
                };

                if (locationId !== null) {
                    createPayload.LocationId = locationId;
                }

                return cy.makePrivateAdminAPICall("POST", "/api/events", createPayload, 200);
            })
            .then(() => cy.makePrivateAdminAPICall("GET", "/api/events", null, 200))
            .then((eventsResponse) => {
                const events = extractCollection(eventsResponse.body, "Events");
                const createdEvent = events.find((event) => event.Title === `${titleBase} - created`);
                expect(createdEvent, "created event found").to.exist;

                expect(createdEvent.LocationText).to.eq("123 Cypress Ave, Test City");
                if (locationId !== null) {
                    expect(createdEvent.LocationId).to.eq(locationId);
                }

                return cy
                    .makePrivateAdminAPICall(
                        "POST",
                        `/api/events/${createdEvent.Id}`,
                        {
                            Title: `${titleBase} - updated`,
                            PinnedCalendars: [calendarId],
                            LocationId: null,
                            LocationText: "456 Updated St, Test City",
                        },
                        200
                    )
                    .then(() => createdEvent.Id);
            })
            .then((eventId) => cy.makePrivateAdminAPICall("GET", `/api/events/${eventId}`, null, 200))
            .then((eventResponse) => {
                expect(eventResponse.body.Title).to.eq(`${titleBase} - updated`);
                expect(eventResponse.body.LocationId).to.eq(null);
                expect(eventResponse.body.LocationText).to.eq("456 Updated St, Test City");

                return cy.makePrivateAdminAPICall("DELETE", `/api/events/${eventResponse.body.Id}`, null, 200);
            });
    });

    it("rejects invalid location id on event update", () => {
        let eventTypeId;
        let calendarId;
        const titleBase = `API Invalid Location Test ${Date.now()}`;

        cy.makePrivateAdminAPICall("GET", "/api/events/types", null, 200)
            .then((typesResponse) => {
                const eventTypes = extractCollection(typesResponse.body, "EventTypes");
                expect(eventTypes.length, "at least one event type").to.be.greaterThan(0);
                eventTypeId = eventTypes[0].Id;
            })
            .then(() => cy.makePrivateAdminAPICall("GET", "/api/calendars", null, 200))
            .then((calendarsResponse) => {
                const calendars = extractCollection(calendarsResponse.body, "Calendars");
                expect(calendars.length, "at least one calendar").to.be.greaterThan(0);
                calendarId = calendars[0].Id;
            })
            .then(() =>
                cy.makePrivateAdminAPICall(
                    "POST",
                    "/api/events",
                    {
                        Title: `${titleBase} - created`,
                        Type: eventTypeId,
                        Desc: "<p>Created for invalid location validation</p>",
                        Start: new Date().toISOString(),
                        End: new Date(Date.now() + 3600000).toISOString(),
                        Text: "<p>Body text</p>",
                        PinnedCalendars: [calendarId],
                    },
                    200
                )
            )
            .then(() => cy.makePrivateAdminAPICall("GET", "/api/events", null, 200))
            .then((eventsResponse) => {
                const events = extractCollection(eventsResponse.body, "Events");
                const createdEvent = events.find((event) => event.Title === `${titleBase} - created`);
                expect(createdEvent, "created event found").to.exist;

                return cy
                    .makePrivateAdminAPICall(
                        "POST",
                        `/api/events/${createdEvent.Id}`,
                        {
                            Title: `${titleBase} - invalid update`,
                            PinnedCalendars: [calendarId],
                            LocationId: 999999999,
                        },
                        400
                    )
                    .then(() => createdEvent.Id);
            })
            .then((eventId) => cy.makePrivateAdminAPICall("DELETE", `/api/events/${eventId}`, null, 200));
    });
});
