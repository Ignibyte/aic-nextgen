--
-- PostgreSQL database dump
--

\restrict aicfixture

-- Dumped from database version 17.11 (Debian 17.11-1.pgdg13+2)
-- Dumped by pg_dump version 18.6

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET transaction_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: pg_trgm; Type: EXTENSION; Schema: -; Owner: -
--

CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;


--
-- Name: EXTENSION pg_trgm; Type: COMMENT; Schema: -; Owner: -
--

COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';


SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: scaffold_books; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scaffold_books (
    id uuid DEFAULT gen_random_uuid() NOT NULL,
    shelf_id bigint NOT NULL,
    title text NOT NULL,
    isbn character varying(13),
    pages integer NOT NULL,
    price numeric(8,2) NOT NULL,
    tags jsonb NOT NULL,
    retired boolean DEFAULT false NOT NULL,
    published_on date,
    label text GENERATED ALWAYS AS ((lower(title) || '''s copy'::text)) STORED NOT NULL,
    worth numeric(12,2) GENERATED ALWAYS AS ((price * (2)::numeric)) STORED NOT NULL,
    CONSTRAINT scaffold_books_isbn_shape CHECK (((isbn IS NULL) OR (length((isbn)::text) = 13))),
    CONSTRAINT scaffold_books_pages_bounded CHECK (((pages > 0) AND (pages < 10000)))
);


--
-- Name: scaffold_loans; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scaffold_loans (
    id bigint NOT NULL,
    book_id uuid NOT NULL,
    task_id bigint,
    borrower character varying(64) NOT NULL,
    lent_at timestamp with time zone NOT NULL,
    returned_at timestamp with time zone
);


--
-- Name: scaffold_loans_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.scaffold_loans_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: scaffold_loans_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.scaffold_loans_id_seq OWNED BY public.scaffold_loans.id;


--
-- Name: scaffold_shelves; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scaffold_shelves (
    id bigint NOT NULL,
    name character varying(100) NOT NULL,
    zone text DEFAULT 'front'::text NOT NULL,
    capacity integer NOT NULL,
    created_at timestamp with time zone NOT NULL,
    CONSTRAINT scaffold_shelves_capacity_positive CHECK ((capacity > 0)),
    CONSTRAINT scaffold_shelves_zone_check CHECK ((zone = ANY (ARRAY['front'::text, 'back'::text])))
);


--
-- Name: scaffold_shelves_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.scaffold_shelves ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.scaffold_shelves_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: scaffold_tasks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scaffold_tasks (
    id bigint NOT NULL,
    title text NOT NULL
);


--
-- Name: scaffold_tasks_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.scaffold_tasks ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.scaffold_tasks_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: scaffold_loans id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_loans ALTER COLUMN id SET DEFAULT nextval('public.scaffold_loans_id_seq'::regclass);


--
-- Name: scaffold_visits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scaffold_visits (
    id bigint NOT NULL,
    path character varying(255) NOT NULL,
    hits smallint DEFAULT 0 NOT NULL,
    tags jsonb DEFAULT '[]'::jsonb NOT NULL,
    payload json,
    seen_at timestamp(0) without time zone,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp with time zone DEFAULT now()
);


--
-- Name: scaffold_visits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.scaffold_visits ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.scaffold_visits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: scaffold_books scaffold_books_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_books
    ADD CONSTRAINT scaffold_books_pkey PRIMARY KEY (id);


--
-- Name: scaffold_loans scaffold_loans_book_id_lent_at_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_loans
    ADD CONSTRAINT scaffold_loans_book_id_lent_at_key UNIQUE (book_id, lent_at);


--
-- Name: scaffold_loans scaffold_loans_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_loans
    ADD CONSTRAINT scaffold_loans_pkey PRIMARY KEY (id);


--
-- Name: scaffold_shelves scaffold_shelves_name_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_shelves
    ADD CONSTRAINT scaffold_shelves_name_key UNIQUE (name);


--
-- Name: scaffold_shelves scaffold_shelves_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_shelves
    ADD CONSTRAINT scaffold_shelves_pkey PRIMARY KEY (id);


--
-- Name: scaffold_tasks scaffold_tasks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_tasks
    ADD CONSTRAINT scaffold_tasks_pkey PRIMARY KEY (id);


--
-- Name: scaffold_books_isbn_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE UNIQUE INDEX scaffold_books_isbn_idx ON public.scaffold_books USING btree (isbn) WHERE (isbn IS NOT NULL);


--
-- Name: scaffold_books_shelf_id_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scaffold_books_shelf_id_idx ON public.scaffold_books USING btree (shelf_id) WHERE (retired = false);


--
-- Name: scaffold_books_tags_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scaffold_books_tags_idx ON public.scaffold_books USING gin (tags jsonb_path_ops);


--
-- Name: scaffold_books_title_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scaffold_books_title_idx ON public.scaffold_books USING gin (title public.gin_trgm_ops);


--
-- Name: scaffold_loans_borrower_lent_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scaffold_loans_borrower_lent_at_idx ON public.scaffold_loans USING btree (borrower, lent_at);


--
-- Name: scaffold_loans_lent_at_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scaffold_loans_lent_at_idx ON public.scaffold_loans USING brin (lent_at);


--
-- Name: scaffold_books scaffold_books_shelf_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_books
    ADD CONSTRAINT scaffold_books_shelf_id_fkey FOREIGN KEY (shelf_id) REFERENCES public.scaffold_shelves(id) ON DELETE CASCADE;


--
-- Name: scaffold_visits scaffold_visits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_visits
    ADD CONSTRAINT scaffold_visits_pkey PRIMARY KEY (id);


--
-- Name: scaffold_loans scaffold_loans_book_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_loans
    ADD CONSTRAINT scaffold_loans_book_id_fkey FOREIGN KEY (book_id) REFERENCES public.scaffold_books(id);


--
-- Name: scaffold_loans scaffold_loans_task_id_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_loans
    ADD CONSTRAINT scaffold_loans_task_id_fkey FOREIGN KEY (task_id) REFERENCES public.scaffold_tasks(id) ON DELETE SET NULL;


--
-- Name: scaffold_permits; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scaffold_permits (
    id bigint NOT NULL,
    code character(8) NOT NULL,
    zone text CONSTRAINT scaffold_permits_zone_not_null NOT NULL,
    holder text,
    latitude double precision,
    fee numeric(8,2) NOT NULL,
    areas jsonb NOT NULL,
    closed_at timestamp with time zone,
    closed_by text,
    contact_email text,
    contact_phone text,
    decision text,
    CONSTRAINT scaffold_permits_closed_whole CHECK (((closed_at IS NULL) = (closed_by IS NULL))),
    CONSTRAINT scaffold_permits_code_shape CHECK ((code ~ '^[A-Z]{2}[0-9]{6}$'::text)),
    CONSTRAINT scaffold_permits_areas_listed CHECK (((jsonb_typeof(areas) = 'array'::text) AND (jsonb_array_length(areas) >= 1))),
    CONSTRAINT scaffold_permits_contact_given CHECK ((num_nonnulls(contact_email, contact_phone) >= 1)),
    CONSTRAINT scaffold_permits_decided CHECK (((decision IS NOT NULL) = COALESCE((zone = 'restricted'::text), false))),
    CONSTRAINT scaffold_permits_fee_in_cents CHECK ((fee = round(fee, 2))),
    CONSTRAINT scaffold_permits_fee_known CHECK (((fee >= (0)::numeric) IS TRUE)),
    CONSTRAINT scaffold_permits_holder_present CHECK (((holder IS NULL) OR (btrim(holder) <> ''::text)))
);


--
-- Name: scaffold_permits_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.scaffold_permits ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.scaffold_permits_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Name: scaffold_permit_stamps; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.scaffold_permit_stamps (
    id bigint NOT NULL,
    permit_id bigint NOT NULL,
    permit_zone text NOT NULL,
    batch text
);


--
-- Name: scaffold_permit_stamps_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

ALTER TABLE public.scaffold_permit_stamps ALTER COLUMN id ADD GENERATED ALWAYS AS IDENTITY (
    SEQUENCE NAME public.scaffold_permit_stamps_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1
);


--
-- Data for Name: scaffold_permits; Type: TABLE DATA; Schema: public; Owner: -
--

COPY public.scaffold_permits (id, code, zone) FROM stdin;
1	AB123456	it's "open"; $$
\.


SELECT pg_catalog.setval('public.scaffold_permits_id_seq', 1, true);


--
-- Name: scaffold_permits scaffold_permits_id_zone_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_permits
    ADD CONSTRAINT scaffold_permits_id_zone_key UNIQUE (id, zone);


--
-- Name: scaffold_permits scaffold_permits_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_permits
    ADD CONSTRAINT scaffold_permits_pkey PRIMARY KEY (id);


--
-- Name: scaffold_permit_stamps scaffold_permit_stamps_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_permit_stamps
    ADD CONSTRAINT scaffold_permit_stamps_pkey PRIMARY KEY (id);


--
-- Name: scaffold_permit_stamps scaffold_permit_stamps_batch_key; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_permit_stamps
    ADD CONSTRAINT scaffold_permit_stamps_batch_key UNIQUE NULLS NOT DISTINCT (permit_id, batch);


--
-- Name: scaffold_permit_stamps_batch_idx; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX scaffold_permit_stamps_batch_idx ON public.scaffold_permit_stamps USING btree (batch);


--
-- Name: scaffold_permit_stamps scaffold_permit_stamps_permit_fkey; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.scaffold_permit_stamps
    ADD CONSTRAINT scaffold_permit_stamps_permit_fkey FOREIGN KEY (permit_id, permit_zone) REFERENCES public.scaffold_permits(id, zone) ON DELETE CASCADE;


--
-- PostgreSQL database dump complete
--

\unrestrict aicfixture

