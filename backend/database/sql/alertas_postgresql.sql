-- =====================================================================
--  Base de datos del Sistema de Alertas Ciudadanas (Laravel + PostgreSQL)
--  Generado a partir de las migraciones de Laravel (php artisan migrate --seed)
--
--  Uso (alternativa a "php artisan migrate --seed"):
--    1) CREATE DATABASE alertas;
--    2) psql -U postgres -d alertas -f alertas_postgresql.sql
--
--  Incluye datos iniciales: roles, tipos de reporte, estaciones de
--  emergencia, 5 reportes de demo y el admin (tel 70000000 / admin123).
--  No requiere PostGIS: las coordenadas se guardan como latitude/longitude.
-- =====================================================================

--
-- PostgreSQL database dump
--


-- Dumped from database version 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1)
-- Dumped by pg_dump version 16.15 (Ubuntu 16.15-0ubuntu0.24.04.1)

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

SET default_tablespace = '';

SET default_table_access_method = heap;

--
-- Name: authority_profiles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.authority_profiles (
    id bigint NOT NULL,
    ci character varying(255) NOT NULL,
    gmail character varying(255) NOT NULL,
    profile_type character varying(255) NOT NULL,
    user_id uuid NOT NULL,
    CONSTRAINT authority_profiles_profile_type_check CHECK (((profile_type)::text = ANY ((ARRAY['policia'::character varying, 'bombero'::character varying, 'paramedico'::character varying])::text[])))
);


--
-- Name: authority_profiles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.authority_profiles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: authority_profiles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.authority_profiles_id_seq OWNED BY public.authority_profiles.id;


--
-- Name: cache; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache (
    key character varying(255) NOT NULL,
    value text NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: cache_locks; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.cache_locks (
    key character varying(255) NOT NULL,
    owner character varying(255) NOT NULL,
    expiration bigint NOT NULL
);


--
-- Name: comments; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.comments (
    id bigint NOT NULL,
    text character varying(250) NOT NULL,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    parent_comment_id bigint,
    report_id bigint NOT NULL,
    creator_id uuid NOT NULL
);


--
-- Name: comments_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.comments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: comments_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.comments_id_seq OWNED BY public.comments.id;


--
-- Name: dispatches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.dispatches (
    id bigint NOT NULL,
    recorded_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    state character varying(255) DEFAULT 'en_curso'::character varying NOT NULL,
    report_id bigint NOT NULL,
    destination_id bigint NOT NULL,
    attended_by uuid NOT NULL,
    CONSTRAINT dispatches_state_check CHECK (((state)::text = ANY ((ARRAY['en_curso'::character varying, 'completado'::character varying, 'cancelado'::character varying])::text[])))
);


--
-- Name: dispatches_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.dispatches_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: dispatches_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.dispatches_id_seq OWNED BY public.dispatches.id;


--
-- Name: emergency_stations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.emergency_stations (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    installation_type character varying(255) NOT NULL,
    latitude double precision NOT NULL,
    longitude double precision NOT NULL,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT emergency_stations_installation_type_check CHECK (((installation_type)::text = ANY ((ARRAY['policia'::character varying, 'bombero'::character varying, 'hospital'::character varying])::text[])))
);


--
-- Name: emergency_stations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.emergency_stations_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: emergency_stations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.emergency_stations_id_seq OWNED BY public.emergency_stations.id;


--
-- Name: failed_jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    uuid character varying(255) NOT NULL,
    connection character varying(255) NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;


--
-- Name: images; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.images (
    id bigint NOT NULL,
    cloudinary_id character varying(255) NOT NULL,
    url character varying(255) NOT NULL,
    uploaded_at timestamp(0) without time zone NOT NULL,
    report_id bigint NOT NULL,
    uploaded_by uuid
);


--
-- Name: images_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.images_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: images_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.images_id_seq OWNED BY public.images.id;


--
-- Name: job_batches; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.job_batches (
    id character varying(255) NOT NULL,
    name character varying(255) NOT NULL,
    total_jobs integer NOT NULL,
    pending_jobs integer NOT NULL,
    failed_jobs integer NOT NULL,
    failed_job_ids text NOT NULL,
    options text,
    cancelled_at integer,
    created_at integer NOT NULL,
    finished_at integer
);


--
-- Name: jobs; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue character varying(255) NOT NULL,
    payload text NOT NULL,
    attempts smallint NOT NULL,
    reserved_at integer,
    available_at integer NOT NULL,
    created_at integer NOT NULL
);


--
-- Name: jobs_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: jobs_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;


--
-- Name: migrations; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);


--
-- Name: migrations_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: migrations_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;


--
-- Name: report_types; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.report_types (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    base_weight integer DEFAULT 1 NOT NULL
);


--
-- Name: report_types_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.report_types_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: report_types_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.report_types_id_seq OWNED BY public.report_types.id;


--
-- Name: reports; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.reports (
    id bigint NOT NULL,
    description text NOT NULL,
    latitude double precision NOT NULL,
    longitude double precision NOT NULL,
    weight integer NOT NULL,
    zone character varying(255),
    verified boolean DEFAULT false NOT NULL,
    expires_at timestamp(0) without time zone NOT NULL,
    status character varying(255) DEFAULT 'activo'::character varying NOT NULL,
    creator_id uuid NOT NULL,
    report_type_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone,
    CONSTRAINT reports_status_check CHECK (((status)::text = ANY ((ARRAY['activo'::character varying, 'resuelto'::character varying, 'vencido'::character varying, 'eliminado'::character varying])::text[])))
);


--
-- Name: reports_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.reports_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: reports_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.reports_id_seq OWNED BY public.reports.id;


--
-- Name: roles; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.roles (
    id bigint NOT NULL,
    name character varying(255) NOT NULL
);


--
-- Name: roles_id_seq; Type: SEQUENCE; Schema: public; Owner: -
--

CREATE SEQUENCE public.roles_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


--
-- Name: roles_id_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: -
--

ALTER SEQUENCE public.roles_id_seq OWNED BY public.roles.id;


--
-- Name: users; Type: TABLE; Schema: public; Owner: -
--

CREATE TABLE public.users (
    id uuid NOT NULL,
    first_name character varying(255) NOT NULL,
    last_name character varying(255) NOT NULL,
    phone character varying(20) NOT NULL,
    password character varying(255) NOT NULL,
    refresh_token character varying(255),
    fcm_token character varying(255),
    last_latitude double precision,
    last_longitude double precision,
    role_id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    deleted_at timestamp(0) without time zone
);


--
-- Name: authority_profiles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.authority_profiles ALTER COLUMN id SET DEFAULT nextval('public.authority_profiles_id_seq'::regclass);


--
-- Name: comments id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comments ALTER COLUMN id SET DEFAULT nextval('public.comments_id_seq'::regclass);


--
-- Name: dispatches id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dispatches ALTER COLUMN id SET DEFAULT nextval('public.dispatches_id_seq'::regclass);


--
-- Name: emergency_stations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.emergency_stations ALTER COLUMN id SET DEFAULT nextval('public.emergency_stations_id_seq'::regclass);


--
-- Name: failed_jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);


--
-- Name: images id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.images ALTER COLUMN id SET DEFAULT nextval('public.images_id_seq'::regclass);


--
-- Name: jobs id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);


--
-- Name: migrations id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);


--
-- Name: report_types id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_types ALTER COLUMN id SET DEFAULT nextval('public.report_types_id_seq'::regclass);


--
-- Name: reports id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reports ALTER COLUMN id SET DEFAULT nextval('public.reports_id_seq'::regclass);


--
-- Name: roles id; Type: DEFAULT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles ALTER COLUMN id SET DEFAULT nextval('public.roles_id_seq'::regclass);


--
-- Data for Name: authority_profiles; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- Data for Name: comments; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- Data for Name: dispatches; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- Data for Name: emergency_stations; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.emergency_stations VALUES (1, 'Comando Departamental Policía SC', 'policia', -17.7833, -63.1821, NULL);
INSERT INTO public.emergency_stations VALUES (2, 'UTOP Equipetrol', 'policia', -17.7565, -63.1968, NULL);
INSERT INTO public.emergency_stations VALUES (3, 'UTOP Sur', 'policia', -17.81, -63.175, NULL);
INSERT INTO public.emergency_stations VALUES (4, 'Cuartel General Bomberos SC', 'bombero', -17.7895, -63.1755, NULL);
INSERT INTO public.emergency_stations VALUES (5, 'Compañía Bomberos Plan 3000', 'bombero', -17.772, -63.168, NULL);
INSERT INTO public.emergency_stations VALUES (6, 'Hospital Japonés', 'hospital', -17.7598, -63.1902, NULL);
INSERT INTO public.emergency_stations VALUES (7, 'Hospital Obrero', 'hospital', -17.7836, -63.1765, NULL);
INSERT INTO public.emergency_stations VALUES (8, 'Clínica Las Américas', 'hospital', -17.783, -63.181, NULL);


--
-- Data for Name: failed_jobs; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- Data for Name: images; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- Data for Name: job_batches; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- Data for Name: jobs; Type: TABLE DATA; Schema: public; Owner: -
--



--
-- Data for Name: migrations; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.migrations VALUES (1, '0001_01_01_000001_create_cache_table', 1);
INSERT INTO public.migrations VALUES (2, '0001_01_01_000002_create_jobs_table', 1);
INSERT INTO public.migrations VALUES (3, '2026_01_01_000001_create_roles_table', 1);
INSERT INTO public.migrations VALUES (4, '2026_01_01_000002_create_users_table', 1);
INSERT INTO public.migrations VALUES (5, '2026_01_01_000003_create_authority_profiles_table', 1);
INSERT INTO public.migrations VALUES (6, '2026_01_01_000004_create_report_types_table', 1);
INSERT INTO public.migrations VALUES (7, '2026_01_01_000005_create_reports_table', 1);
INSERT INTO public.migrations VALUES (8, '2026_01_01_000006_create_images_table', 1);
INSERT INTO public.migrations VALUES (9, '2026_01_01_000007_create_comments_table', 1);
INSERT INTO public.migrations VALUES (10, '2026_01_01_000008_create_emergency_stations_table', 1);
INSERT INTO public.migrations VALUES (11, '2026_01_01_000009_create_dispatches_table', 1);


--
-- Data for Name: report_types; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.report_types VALUES (1, 'Accidente de tránsito', 9);
INSERT INTO public.report_types VALUES (2, 'Robo agravado', 9);
INSERT INTO public.report_types VALUES (3, 'Incendio', 10);
INSERT INTO public.report_types VALUES (4, 'Emergencia médica', 9);
INSERT INTO public.report_types VALUES (5, 'Violencia familiar', 8);
INSERT INTO public.report_types VALUES (6, 'Robo menor', 4);
INSERT INTO public.report_types VALUES (7, 'Disturbio', 5);


--
-- Data for Name: reports; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.reports VALUES (1, 'Accidente de tránsito en Av. Banzer y 2do anillo', -17.7833, -63.1812, 9, 'Centro', true, '2026-10-23 02:12:18', 'activo', '01a1427b-4b45-71e5-b9a6-411ae3b3a488', 1, '2026-10-16 02:12:18', '2026-10-16 02:12:18', NULL);
INSERT INTO public.reports VALUES (2, 'Robo agravado reportado por vecinos', -17.79, -63.195, 9, 'Equipetrol', false, '2026-10-23 02:12:18', 'activo', '01a1427b-4b45-71e5-b9a6-411ae3b3a488', 2, '2026-10-16 02:12:18', '2026-10-16 02:12:18', NULL);
INSERT INTO public.reports VALUES (3, 'Incendio en bodega comercial', -17.775, -63.17, 10, 'Plan 3000', true, '2026-10-23 02:12:18', 'activo', '01a1427b-4b45-71e5-b9a6-411ae3b3a488', 3, '2026-10-16 02:12:18', '2026-10-16 02:12:18', NULL);
INSERT INTO public.reports VALUES (4, 'Emergencia médica — persona inconsciente', -17.768, -63.205, 9, 'Urbari', false, '2026-10-23 02:12:18', 'activo', '01a1427b-4b45-71e5-b9a6-411ae3b3a488', 4, '2026-10-16 02:12:18', '2026-10-16 02:12:18', NULL);
INSERT INTO public.reports VALUES (5, 'Violencia familiar en zona residencial', -17.801, -63.188, 8, 'La Guardia', true, '2026-10-23 02:12:18', 'activo', '01a1427b-4b45-71e5-b9a6-411ae3b3a488', 5, '2026-10-16 02:12:18', '2026-10-16 02:12:18', NULL);


--
-- Data for Name: roles; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.roles VALUES (1, 'usuario');
INSERT INTO public.roles VALUES (2, 'autoridad');
INSERT INTO public.roles VALUES (3, 'admin');


--
-- Data for Name: users; Type: TABLE DATA; Schema: public; Owner: -
--

INSERT INTO public.users VALUES ('01a1427b-4b45-71e5-b9a6-411ae3b3a488', 'admin', 'sistema', '70000000', '$2y$12$HZriJJ4oVEGysXfQLyFfj./8UGh9As4nUUxYmJgaYsQlT97zQEi6K', NULL, NULL, NULL, NULL, 3, '2026-10-16 02:12:18', '2026-10-16 02:12:18', NULL);


--
-- Name: authority_profiles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.authority_profiles_id_seq', 1, false);


--
-- Name: comments_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.comments_id_seq', 1, false);


--
-- Name: dispatches_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.dispatches_id_seq', 1, false);


--
-- Name: emergency_stations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.emergency_stations_id_seq', 8, true);


--
-- Name: failed_jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);


--
-- Name: images_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.images_id_seq', 1, false);


--
-- Name: jobs_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);


--
-- Name: migrations_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.migrations_id_seq', 11, true);


--
-- Name: report_types_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.report_types_id_seq', 7, true);


--
-- Name: reports_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.reports_id_seq', 5, true);


--
-- Name: roles_id_seq; Type: SEQUENCE SET; Schema: public; Owner: -
--

SELECT pg_catalog.setval('public.roles_id_seq', 3, true);


--
-- Name: authority_profiles authority_profiles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.authority_profiles
    ADD CONSTRAINT authority_profiles_pkey PRIMARY KEY (id);


--
-- Name: authority_profiles authority_profiles_user_id_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.authority_profiles
    ADD CONSTRAINT authority_profiles_user_id_unique UNIQUE (user_id);


--
-- Name: cache_locks cache_locks_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache_locks
    ADD CONSTRAINT cache_locks_pkey PRIMARY KEY (key);


--
-- Name: cache cache_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);


--
-- Name: comments comments_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comments
    ADD CONSTRAINT comments_pkey PRIMARY KEY (id);


--
-- Name: dispatches dispatches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dispatches
    ADD CONSTRAINT dispatches_pkey PRIMARY KEY (id);


--
-- Name: emergency_stations emergency_stations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.emergency_stations
    ADD CONSTRAINT emergency_stations_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);


--
-- Name: failed_jobs failed_jobs_uuid_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_uuid_unique UNIQUE (uuid);


--
-- Name: images images_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.images
    ADD CONSTRAINT images_pkey PRIMARY KEY (id);


--
-- Name: job_batches job_batches_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.job_batches
    ADD CONSTRAINT job_batches_pkey PRIMARY KEY (id);


--
-- Name: jobs jobs_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);


--
-- Name: migrations migrations_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);


--
-- Name: report_types report_types_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.report_types
    ADD CONSTRAINT report_types_pkey PRIMARY KEY (id);


--
-- Name: reports reports_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reports
    ADD CONSTRAINT reports_pkey PRIMARY KEY (id);


--
-- Name: roles roles_name_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_name_unique UNIQUE (name);


--
-- Name: roles roles_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.roles
    ADD CONSTRAINT roles_pkey PRIMARY KEY (id);


--
-- Name: users users_phone_unique; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_phone_unique UNIQUE (phone);


--
-- Name: users users_pkey; Type: CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);


--
-- Name: cache_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_expiration_index ON public.cache USING btree (expiration);


--
-- Name: cache_locks_expiration_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX cache_locks_expiration_index ON public.cache_locks USING btree (expiration);


--
-- Name: comments_report_id_parent_comment_id_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX comments_report_id_parent_comment_id_index ON public.comments USING btree (report_id, parent_comment_id);


--
-- Name: failed_jobs_connection_queue_failed_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX failed_jobs_connection_queue_failed_at_index ON public.failed_jobs USING btree (connection, queue, failed_at);


--
-- Name: jobs_queue_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX jobs_queue_index ON public.jobs USING btree (queue);


--
-- Name: reports_deleted_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reports_deleted_at_index ON public.reports USING btree (deleted_at);


--
-- Name: reports_expires_at_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reports_expires_at_index ON public.reports USING btree (expires_at);


--
-- Name: reports_latitude_longitude_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX reports_latitude_longitude_index ON public.reports USING btree (latitude, longitude);


--
-- Name: users_fcm_token_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_fcm_token_index ON public.users USING btree (fcm_token);


--
-- Name: users_last_latitude_last_longitude_index; Type: INDEX; Schema: public; Owner: -
--

CREATE INDEX users_last_latitude_last_longitude_index ON public.users USING btree (last_latitude, last_longitude);


--
-- Name: authority_profiles authority_profiles_user_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.authority_profiles
    ADD CONSTRAINT authority_profiles_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;


--
-- Name: comments comments_creator_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comments
    ADD CONSTRAINT comments_creator_id_foreign FOREIGN KEY (creator_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: comments comments_parent_comment_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comments
    ADD CONSTRAINT comments_parent_comment_id_foreign FOREIGN KEY (parent_comment_id) REFERENCES public.comments(id) ON DELETE CASCADE;


--
-- Name: comments comments_report_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.comments
    ADD CONSTRAINT comments_report_id_foreign FOREIGN KEY (report_id) REFERENCES public.reports(id) ON DELETE CASCADE;


--
-- Name: dispatches dispatches_attended_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dispatches
    ADD CONSTRAINT dispatches_attended_by_foreign FOREIGN KEY (attended_by) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: dispatches dispatches_destination_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dispatches
    ADD CONSTRAINT dispatches_destination_id_foreign FOREIGN KEY (destination_id) REFERENCES public.emergency_stations(id) ON DELETE RESTRICT;


--
-- Name: dispatches dispatches_report_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.dispatches
    ADD CONSTRAINT dispatches_report_id_foreign FOREIGN KEY (report_id) REFERENCES public.reports(id) ON DELETE CASCADE;


--
-- Name: images images_report_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.images
    ADD CONSTRAINT images_report_id_foreign FOREIGN KEY (report_id) REFERENCES public.reports(id) ON DELETE CASCADE;


--
-- Name: images images_uploaded_by_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.images
    ADD CONSTRAINT images_uploaded_by_foreign FOREIGN KEY (uploaded_by) REFERENCES public.users(id) ON DELETE SET NULL;


--
-- Name: reports reports_creator_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reports
    ADD CONSTRAINT reports_creator_id_foreign FOREIGN KEY (creator_id) REFERENCES public.users(id) ON DELETE RESTRICT;


--
-- Name: reports reports_report_type_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.reports
    ADD CONSTRAINT reports_report_type_id_foreign FOREIGN KEY (report_type_id) REFERENCES public.report_types(id) ON DELETE RESTRICT;


--
-- Name: users users_role_id_foreign; Type: FK CONSTRAINT; Schema: public; Owner: -
--

ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_role_id_foreign FOREIGN KEY (role_id) REFERENCES public.roles(id) ON DELETE RESTRICT;


--
-- PostgreSQL database dump complete
--


